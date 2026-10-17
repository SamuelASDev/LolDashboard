# Arquitetura e decisões

## Entrada CLI para worker temporário

scripts/cron-worker.php é uma entrada PHP CLI para hospedagem com disparos periódicos. Usa caminhos relativos ao próprio arquivo, sem montar comandos shell, e chama queue:work pelo Console Kernel com conexão/fila de configuração. Um lock de arquivo PHP não bloqueante em storage/framework/cron-worker.lock abrange bootstrap e execução; processos sobrepostos saem sem consumir jobs. O sistema operacional libera o lock quando o processo termina. Esse lock não substitui as travas existentes por execução/partida ou o cache compartilhado. O modo --check valida a inicialização sem processar fila. Scheduler permanece separado. Falhas não imprimem mensagens/traces de exceções potencialmente sensíveis; guard CLI recusa HTTP.

## Organização

O projeto continua um monólito Laravel com Blade. A modularização acompanha responsabilidades reais, sem duplicar o Eloquent com repositórios genéricos.

| Camada | Caminho | Responsabilidade |
| --- | --- | --- |
| HTTP | `app/Http/Controllers` | Requisições, escopo/autorização e respostas |
| Validação | `app/Http/Requests/ContaRequest.php` | Normalização, formato, limites e mensagens |
| Casos de uso | `app/Actions/Accounts`, `Sync`, `Users` | Alterações de estado e invariantes |
| Leitura | `app/Queries` | Ranking e agregações SQL, sem consultas em views |
| Integração | `app/Services/RiotService.php` | Hosts fixos, encoding, autenticação, orçamento e erros seguros |
| Domínio | `app/Enums`, `app/Models`, `app/Policies` | Regiões, relacionamentos, autorização e identidade |
| Assíncrono | `app/Jobs/SyncAccount.php` | Etapa limitada, exclusão mútua, versão e reagendamento |
| Operação | `app/Console/Commands` | Recuperação de tarefas e promoção administrativa |
| Apresentação | `resources/views`, `resources/js` | Componentes Blade e polling Fetch/Alpine |

`ContaController` não concentra rede, importação, streaming HTML ou agrupamentos em memória. `SyncController` agenda, consulta estado e cancela. Não há pausa de segundos nem retentativa infinita na requisição web.

## Identidade e legado

- `user_id` é o limite de acesso. Policies e consultas com escopo impedem acesso por ID de outro dono.
- `puuid` confirma a identidade Riot. Há unicidade por usuário/região/PUUID e fingerprint para novos cadastros.
- Depois da confirmação, só o apelido é editável. Renomeações são atualizadas via Account-V1 por PUUID; trocar jogador/região requer novo cadastro, evitando mistura.
- Duplicatas anteriores não são apagadas. Ao confirmar a mesma identidade, o segundo cadastro recebe erro controlado; o usuário decide qual manter.
- Partidas antigas começam com `identity_puuid = null`. Somente a resposta da partida contendo o PUUID atual confirma o vínculo. Registros indisponíveis na API ficam preservados, fora das métricas.
- `(match_id, conta_id)` permanece único. Match compartilhado pode existir uma vez por conta.
- A migration nova adiciona schema sem limpeza. O rollback antigo para match ID globalmente único recusa duplicatas antes de modificar índices.

## Máquina de sincronização

~~~mermaid
flowchart LR
    Pedido[POST autenticado] --> Fila[Run queued + job]
    Fila --> Snapshot[Resolver identidade e ranking]
    Snapshot --> Historico[Buscar página de IDs]
    Historico --> Partida[Importar uma partida]
    Partida --> Partida
    Partida --> Historico
    Historico --> Concluido[completed: API sem mais IDs]
    Snapshot --> Espera[retrying: erro transitório]
    Historico --> Espera
    Partida --> Espera
    Espera --> Fila
    Espera --> Falha[failed: orçamento esgotado]
~~~

Cada job faz uma etapa: snapshot (até duas chamadas), página (uma) ou partida (uma). Jogos confirmados são filtrados em lote por página. A importação não para por `wins + losses`; percorre o histórico disponível com limite operacional explícito.

`sync_runs` guarda fase, cursor, IDs pendentes, contadores, versão, mensagem segura e próximo horário permitido. Chave única de conta ativa impede execuções simultâneas; cache lock impede dois jobs na mesma etapa; versão torna jobs antigos/duplicados inofensivos.

Partida e checkpoint são gravados na mesma transação curta. Rede fica fora de transações. Falhas preservam dados confirmados. Ranking só muda com resposta válida; liga vazia zera métricas e define UNRANKED, enquanto erro preserva o snapshot anterior.

Erros transitórios têm tentativas finitas e respeitam `Retry-After`, sem bloquear worker dormindo. Falhas permanentes encerram com mensagem controlada. Há timeout HTTP, timeout de job e prazo total. Recuperação reagenda runs inativos há 5 minutos respeitando `available_at`, lock e prazo. Se o processo cair entre checkpoint e dispatch, retoma o checkpoint.

Nova execução após erro/cancelamento começa pelos IDs recentes e ignora os já confirmados. Limite de páginas gera `partial`, sem afirmar cobertura total.

## API e segurança

`RiotRegion` admite seis plataformas. Account/Match usam Americas/Europe/Asia; League usa plataforma. Nenhum texto livre define host. Segmentos são codificados, chave segue em `X-Riot-Token` e redirects são recusados.

Cliente contabiliza janelas de 1 e 120 segundos em cache compartilhado com lock. Resposta 429 participa do orçamento finito de falhas externas; bloqueio local de orçamento apenas reagenda, limitado pelo prazo total.

Exceções externas viram mensagens públicas controladas. Logs inesperados guardam apenas classe e ID da execução. Respostas autenticadas não são cacheadas; há headers contra enquadramento e MIME sniffing.

E-mail verificado é obrigatório no dashboard/contas/admin; perfil e verificação continuam acessíveis ao autenticado. Cargo não é mass assignable; mutações administrativas são explícitas e auditadas.

## Métricas e limites

Queries agregam no banco em períodos móveis UTC; apresentação usa Brasília. Não se infere temporada/split oficial por contadores. Ranking atual e histórico por período são conjuntos diferentes.

KDA é `(kills + assists) / deaths` acumulado, não média de razões individuais. Sem mortes e com jogos mostra “Perfeito”; sem jogos, “—”. Duas contas na mesma partida são duas participações.

A heurística de remake usa `gameEndedInEarlySurrender` e duração inferior a 300 segundos. Preserva o registro marcado, excluindo-o dos agregados. Rendição mais longa não é descartada. A heurística não comprova como a liga contabiliza o jogo.

O scraping antigo e seus fallbacks fixos foram removidos na refatoração inicial. Os cortes foram restaurados por uma integração pública diária separada, descrita abaixo, sem reutilizar esses fallbacks. A fórmula antiga de decay foi removida; o acompanhamento restaurado em 17/10 usa projeção explícita e referência opcional do cliente, descritos abaixo.

A profundidade do histórico depende da Riot. Offset pode se deslocar durante partidas novas; execuções futuras revarrem IDs recentes. Não há RSO nem prova de titularidade. Testes simulam a Riot, sem validar chave real.

SQLite atende desenvolvimento/uso pequeno; múltiplos workers precisam de avaliação de contenção, banco e cache compartilhados. Locks de linha dependem do driver; unicidade no banco é a barreira final. Operação real, backup, carga e entrega de e-mail dependem da infraestrutura.

## Histórico detalhado e análise pessoal — 16/10/2026

- Partida continua sendo a projeção de um jogador de uma conta: identidade confirmada, fila, resultado e indicadores pessoais. Os novos campos são anuláveis para preservar o legado sem inventar zeros.
- RiotMatch guarda um único payload Match-V5 por match_id e metadados da partida; MatchParticipant normaliza os participantes. Não existem rotas públicas para consultar esse cache. A página acessa participantes somente através de partidas de uma conta do usuário autenticado.
- O cache persiste no banco e é reaproveitado entre contas. Um lock por match_id abrange consulta externa, normalização e checkpoint, evitando chamadas concorrentes duplicadas. A exibição usa eager loading e projeção sem payload.
- ImportMatch valida identidade, fila, slots e estatísticas antes de gravar e mantém a operação transacional. teamPosition tem preferência sobre individualPosition; oponente é a posição correspondente no outro time, sem inferência externa.
- AccountStatistics agrega por SQL; AccountInsights percorre o recorte em ordem temporal com cursor, mantendo somente últimos 20 jogos e máximos pessoais, mais acumuladores por data/grupo. O custo do processamento cresce com as partidas do recorte; carga em grande escala ainda não foi medida.
- Filtros comuns: período, Solo/Duo ou Flex, campeão, campeão adversário, posição e resultado. Os padrões, sequências e recordes descrevem o mesmo recorte filtrado. Médias por minuto são médias das taxas por partida; KDA global continua sendo razão dos totais.
- AccountInsights inclui lado, duração, dia/horário de Brasília, resultado anterior, rendição, adversário e companheiros no mesmo time. Coocorrência não comprova premade; nenhuma comparação com média populacional é calculada.
- Na aba Padrões, AccountInsights também agrega os dez jogadores adversários mais frequentes em duas listas: mesma posição do jogador em cada partida e qualquer posição do time inimigo. As duas consultas SQL usam o Builder autorizado e filtrado, agrupam por PUUID e contam partidas distintas por jogador, com vitórias da conta consultada. Não confundem campeões com jogadores nem fazem novas chamadas à Riot. A lista da mesma posição exige posição conhecida, única em cada time e participante da própria conta identificado nessa posição. Duplicidades/posições desconhecidas podem continuar na lista geral, mas não definem confronto direto. As consultas adicionais só são executadas ao abrir Padrões; nomes permanecem escapados em Blade.
- A sincronização padrão é recent: verifica até 60 jogos (páginas padrão de 20), ou encerra após uma página integralmente conhecida e detalhada. full percorre somente a janela da temporada selecionada até esgotar a API ou os limites operacionais. As duas modalidades enriquecem resumos antigos reencontrados e preservam checkpoints. Runs anteriores conservam full.
- A fase snapshot persiste a identidade resolvida no contexto para não repetir Account-V1 se o orçamento acabar antes de League-V4. Ranking, observação RankSnapshot e mudança de fase são confirmados juntos. O ranking do cabeçalho e os snapshots continuam Solo/Duo, mesmo durante uma importação Flex; última atividade Solo/Duo não é sobrescrita por Flex.
- Não há espera fixa de dois segundos entre etapas bem-sucedidas. O orçamento compartilhado configurado continua ativo; headers de limite/contagem e Retry-After podem impor uma pausa global conservadora. Janelas reportadas são aguardadas integralmente quando saturadas; não se assume uma taxa maior que a configurada.
- Ícones usam catálogo versionado resources/data/riot-assets.json, obtido do Data Dragon público sem chave. RiotAssets resolve arquivos permitidos desse catálogo; páginas não baixam JSON nem chamam Riot API. Imagens são carregadas pelo navegador no CDN oficial. Ícones atuais não reconstituem atributos de itens históricos.
- RankSnapshot registra somente observações futuras de liga; não reconstrói LP por partida, MMR ou temporadas anteriores. A tela distingue o período dos snapshots do recorte das partidas.

Referências consultadas: [documentação oficial da Riot](https://support-developer.riotgames.com/hc/en-us/articles/22698698001939-League-of-Legends) e perfil/funcionalidades do LeagueOfGraphs indicados pelo usuário. A implementação usa os dados da Riot, sem scraping do LeagueOfGraphs.

## Destinos após autenticação — 16/10/2026

RedirectAfterAuthentication centraliza os redirecionamentos após login, confirmação de senha e verificação/reenvio de e-mail. Usuário não verificado segue para verification.notice, preservando o destino até verificar. Usuário verificado só retorna a URL da mesma origem, dentro do caminho-base, correspondente a uma página GET permitida e acessível. Destinos de contas exigem propriedade atual; administração exige cargo apropriado. Rotas inexistentes, IDs apagados, formulários de autenticação e endpoints de progresso não são páginas de chegada. O fallback é dashboard. Novas páginas de chegada devem ser explicitamente incluídas nessa ação e testadas.

Cadastro regenera a sessão, elimina destino herdado e abre a verificação diretamente. Logout e exclusão do perfil usam a rota nomeada home. GET /contas é um redirecionamento para dashboard que preserva days. URLs inexistentes acessadas diretamente continuam respondendo 404; não há interceptação global de erros para esconder recursos ausentes.

## Barras de progresso e cortes diários — 16/10/2026

RankProgress calcula o avanço entre divisões para Ferro–Diamante (0–400 LP por elo, considerando a divisão atual). As barras do dashboard e dos detalhes usam esse mesmo cálculo, com valores limitados ao intervalo visual. Contas sem classificação sincronizada não recebem progresso inventado.

Cada conta mostra somente a meta imediatamente seguinte: Mestre usa o corte de Grão-Mestre; Grão-Mestre usa o corte de Desafiante; Desafiante não mostra barra de próximo elo. A porcentagem usa LP atual/corte consultado, sem supor um intervalo fixo entre os elos. Atingir a referência não é promessa de promoção/permanência. Os indicadores são explicitamente Solo/Duo, inclusive quando o filtro de partidas estiver em Flex.

ReplaysCutoffSource consulta exclusivamente https://www.replays.lol/cutoff/BR/grandmaster, sem token Riot, redirecionamentos ou host fornecido por usuário. O JSON público embutido nessa página contém ambos os cortes por plataforma. O parser confere a página, região, tipos, intervalos, consistência entre os cortes e horário de atualização. Mudanças de estrutura ou dados antigos/inválidos não são substituídos por números arbitrários.

RefreshRankCutoffs realiza uma única consulta global por dia civil de Brasília, com lock e tentativa persistida antes de acessar a rede. Os 12 registros (seis plataformas e dois elos) são compartilhados entre usuários. Atualizações de cada par de cortes são atômicas. Erros preservam valor, fonte e data da última consulta válida, registrando falha; a interface identifica valores desatualizados (falha ou mais de 36 horas). As páginas só leem rank_cutoffs, em lote, sem rede ou consulta por conta em Blade.

O comando riot:cutoffs é agendado às 03:10 America/Sao_Paulo; --force é uma opção operacional explícita para repetir a consulta do dia. Não existe fallback 600, fórmula GM+400 ou integração desse scraper no job individual de contas.

## Temporadas cadastradas e limites das buscas

Season mantém o catálogo com estados draft, active e ended. A migration 000004 cadastra apenas 2026 como atual e conserva os seis horários regionais de início já adotados (08/01 ao meio-dia do servidor; BR 15h UTC). As próximas temporadas são cadastradas pelo administrador sem iniciar a contagem.

ActivateSeason trava o catálogo em ordem de ID e faz a troca em uma transação: encerra a anterior e inicia a escolhida no mesmo segundo UTC. active_slot nullable com índice único impede duas atuais; temporadas iniciadas não podem ser reativadas. A operação registra season.activated com ator e IDs. Nenhuma partida é excluída na troca.

SelectedSeason valida o filtro e impede selecionar rascunhos. Dashboard e conta usam a atual quando não há parâmetro season. AccountStatistics aplica o intervalo por região, início inclusivo e fim exclusivo, antes de todas as agregações, gráficos, confrontos e histórico. Snapshots usam o intervalo de observação da temporada e o período móvel. Ranking de cabeçalho/cortes continua explicitamente atual.

partidas.started_at preserva o início calculado pelo importador. A migração preenche dados existentes a partir do cache ou fim menos duração, em lotes, preservando os registros. Para resumos antigos sem esses dados, o filtro usa played_at como aproximação; nova importação pode completar o início exato. O intervalo histórico imutável define a temporada, inclusive para uma partida iniciada antes da troca e terminada depois.

HistoryWindow recebe Season e persiste season_id, nome, start e end Unix no contexto de StartSync antes do dispatch. O fim fica congelado em min(agora, encerramento). RiotService envia startTime/endTime no Match-V5; recent limita a 60 jogos e full percorre o intervalo da temporada escolhida. Temporadas encerradas podem ser reimportadas, conforme disponibilidade da API.

AdvanceSync limita checkpoints pelo encerramento persistido, com compatibilidade para janelas antigas identificadas pelo nome 2026. ImportMatch relê a temporada com lock na transação antes da gravação, evitando atravessar uma troca concorrente com janela desatualizada. Página repetida e resposta fora do intervalo continuam interrompendo a busca.

O início regional de 2026 conserva as referências já registradas nas entradas anteriores. Novas temporadas seguem a troca administrativa simultânea nas regiões suportadas, sem presumir datas futuras oficiais.

## Apresentação roxa/dourada e confrontos por posição

O tema usa tokens Tailwind 4 em app.css, componentes reutilizáveis e fontes locais do sistema; não foi adicionada biblioteca de gráficos nem dependência de fonte remota. Painéis, formulários, shell, autenticação e dashboard compartilham a paleta. Splash decorativa é resolvida por RiotAssets usando somente o catálogo permitido e CDN oficial; não há texto livre definindo host.

AccountMatchups agrega o mesmo Builder com escopo de usuário, conta, identidade, fila, período e filtros. Agrupa posição do jogador + campeão adversário, mantendo o mesmo campeão separado entre posições. A consulta adicional é executada somente na aba Confrontos. Não acessa base populacional nem consulta outros jogadores na Riot.

PersonalCharts transforma acumuladores diários/RankSnapshots em pontos de exibição. Gráficos de barras, anel e linha são renderizados em Blade/SVG/CSS, com alternativas textuais e tabela. Não há rede no backend da apresentação nem aumento do bundle JavaScript. Os dados do gráfico diário são limitados a 21 dias ativos para legibilidade; o histórico completo do recorte permanece disponível em tabela.

As abas são divididas em partials (overview, matchups, patterns, progress e recent-form), apoiadas por componentes role-icon, opponent-row, match-lineup, daily-chart, trend-chart e winrate-ring. Posicionamento responsivo usa CSS; recolhimento de filtros e densidade do histórico usam Alpine sobre elementos nativos. Sem JavaScript, filtros continuam abertos e dados/tabelas continuam renderizados.

ImportMatch exige adversário único na mesma posição do time oposto antes de atribuir o confronto. A prévia dos times também não escolhe um participante arbitrariamente quando a posição está duplicada. Dados de jogadores/campeões permanecem escapados em Blade.

## Decay Mestre+ — 17/10/2026

AccountDecay recebe contas previamente autorizadas e calcula o acompanhamento atual Solo/Duo, independente dos filtros de histórico e da temporada selecionada na tela. Somente PUUID confirmado, ranking consultado e tier MASTER/GRANDMASTER/CHALLENGER habilitam o componente. Nenhuma leitura da tela chama a Riot. Uma consulta de partidas em lote, ordenada e com cursor, compara identity_puuid ao PUUID da própria conta, restringe fila 420 e ignora remakes, resumos sem detalhes e partidas futuras.

Sem referência, a simulação começa com limites de 0 e 14 dias há 14 dias (ou no início regional da temporada atual, se posterior). Cada partida estende cada limite em um dia, a partir do maior entre o prazo anterior e o término da partida, com teto de 14 dias desde esse término. Reserva esgotada não gera dívida negativa. O resultado é um intervalo estimado; pressupõe Mestre+ no período e partidas importadas suficientes, sem atestar cobertura ou o instante de promoção. Não se aplica 14 dias automaticamente desde a última partida.

A migration aditiva 2026_10_17_000001 adiciona contas.decay_reference JSON anulável. SaveDecayReference grava somente dias validados entre 0 e 14, instante UTC do servidor, PUUID, região e ID da temporada atual; esses campos não entram no fillable público. A ação relê a conta pelo dono sob lock transacional. PUT/DELETE autenticados e verificados não aceitam referência de outra conta. Não há data arbitrária enviada pelo formulário.

Quando informada pelo usuário, a referência substitui os limites iniciais; jogos terminados antes ou no instante dela não recebem crédito novamente. O cálculo é refeito pelos dados persistidos, sem incrementar saldo em cada importação e sem duplicar crédito em reimportações. Troca de temporada, PUUID ou região invalida a referência na leitura. Snapshot válido fora de Mestre+ remove a referência; falha externa a preserva. O snapshot trava e relê a conta para não sobrescrever um ajuste concorrente com dados carregados anteriormente.

O tempo usa períodos de 24 horas: é aproximação, pois o desconto oficial é diário. Estados: proteção estimada, alerta até 48 horas, reserva incerta quando o intervalo inclui zero e possível decay quando o limite superior acaba. Nunca se declara perda efetiva confirmada, subtrai LP ou infere decay pela diferença de snapshots. Datas exibidas em Brasília. O aviso usa solo_history_synced_at e solo_history_window da temporada atual, além da atualização do ranking. Exige janela recente que cubra os últimos 14 dias ou o início da temporada, se posterior; buscas recentes limitadas não atestam automaticamente toda essa janela. history_synced_at permanece como informação genérica, sem autorizar a remoção do aviso.

Fonte verificada em 17/10/2026: [Riot — decaimento por inatividade](https://support.riotgames.com/en-us/league-of-legends/gameplay/placements-promotions-series-demotions-and-decay). Mestre+ recebe 1 dia por partida, máximo 14, penalidade diária de 75 LP sem reserva. Diamante também tem decay oficial, porém permanece fora deste recurso por decisão de escopo. Não se presume acesso ao banco oficial de dias pela League-V4 utilizada.


## Dashboard consolidado de todas as contas — 17/10/2026

AccountAnalytics centraliza validação dos filtros, Builder autorizado, opções de seleção, estatísticas, insights, confrontos e histórico paginado. ContaController usa o mesmo caso de leitura no dashboard (todas as contas do usuário) e nos detalhes (uma conta previamente autorizada). Fila, temporada, período, campeão, posição, adversário-campeão e resultado aplicam-se antes das agregações. As consultas ignoram legado sem identidade e remakes pelas regras já existentes de AccountStatistics.

O dashboard oferece as sete abas das contas: visão geral, partidas, campeões, confrontos, padrões, recordes e evolução. Tabs, filtros e partials são compartilhados; analyticsRoute/analyticsParams definem o destino dos links, mantendo navegação e paginação no consolidado ou na conta correta. Não se utiliza uma conta arbitrária ou a última conta dos cards como origem dos links globais. O histórico carrega a conta em eager loading e mostra a origem de cada participação; recordes usam um mapa de nomes das contas autorizadas.

Contagens, vitórias/derrotas, K/D/A e métricas aditivas são somados a partir das participações filtradas. Winrate é razão dos totais e KDA é razão entre somas; médias por indicador usam todas as amostras com valor, sem média de médias por conta. Taxas por minuto continuam sendo a média das taxas de cada partida, conforme a semântica já adotada. Valores ausentes não viram zeros. AccountInsights expõe sums para apresentar totais de farm, ouro, dano, visão, sentinelas e demais métricas aditivas ao lado das médias no consolidado.

A mesma partida pode contribuir uma participação por conta do dono, inclusive com resultados distintos em times opostos. Isso é explicitado no dashboard e nos adversários recorrentes. Séries e padrões de resultado anterior seguem ordem cronológica das participações entre contas, com desempate por ID. Não se afirma que o total represente partidas únicas ou dias/LP somados.

AccountRankHistory consulta observações Solo/Duo em lote por IDs autorizados, região, temporada e período, com groupLimit de 60 observações por conta no SQL. A visualização gera um gráfico separado por conta. Não se somam classificações/LP de séries independentes nem se reconstrói ranking histórico. RankProgress e AccountDecay continuam por conta. Páginas frias que usam insights ainda têm custo proporcional ao recorte; carga em produção segue sem benchmark.

## Caderno pessoal e revisão de confiabilidade — 17/10/2026

A migration aditiva 2026_10_17_000002 cria player_notes (usuário + PUUID únicos), match_notes (usuário + match_id únicos), filter_favorites e personal_goals. Não há anotações públicas nem compartilhamento pelo cache Riot. Exclusão de usuário remove seus itens; exclusão de conta preserva anotações e transforma seu favorito em filtro global. O histórico Flex é preservado, mas não aparece nas telas e não é importado: rotas aceitam somente 420, StartSync valida argumentos e jobs/AdvanceSync cancelam execuções antigas de outras filas antes da rede.

PlayerHistory parte de contas do usuário, identidade confirmada, Solo/Duo sem remake e participantes identificados dos times 100/200. Pesquisa LIKE escapa %, _ e o próprio escape; usa parâmetros, sem SQL construído com o nome. Página do jogador só existe para encontro autorizado ou anotação própria preservada. Filtro aliado/adversário muda os encontros exibidos. Identidade é PUUID, não nome. Contagem por participação é explícita. Histórico paginado carrega placar/conta em lote; anotações são consultadas por usuário e IDs da página, fora do cache de métricas.

SavePersonalWorkspace concentra escritas, limites e exclusão de favoritos/metas. Favoritos guardam apenas filtros validados e conta autorizada, nunca uma URL fornecida pelo navegador. Metas usam partidas/vitórias/winrate de todas as contas confirmadas em intervalo UTC validado de até um ano, com amostra exibida. PersonalWorkspace reúne a leitura do caderno. Texto privado sempre passa pelo escaping Blade.

AccountAnalytics limita o carregamento de participantes à aba de histórico, evita o cursor completo em Campeões/Confrontos/Partidas e consulta recorrência somente em Padrões. Insights usam cache de 60 segundos com usuário, conta, temporada, filtros, minuto e assinatura SQL; gravações de Partida/MatchParticipant renovam revisão de cache. Escritas em lote fora dos modelos têm invalidação pela assinatura e, no máximo, pelo TTL quando a assinatura não muda. Cache nunca contém anotações; não substitui autorização. ImportMatch trava a conta antes da temporada e só aceita dez participantes com PUUIDs/slots únicos e cinco jogadores por time.

Limites de recurso em config/workspace.php: 20 contas, três execuções ativas/usuário, 100 buscas recentes e dez completas por dia UTC. StartSync verifica cotas sob o mesmo lock do usuário que serializa criações; preserva uma execução existente. Lote tolera contas que não podem ser agendadas e informa quantas foram aceitas. Rate limit público tem chaves separadas para cadastro e recuperação. Recuperação retorna mensagem genérica para destinatário existente, ausente ou limitado pelo broker.
