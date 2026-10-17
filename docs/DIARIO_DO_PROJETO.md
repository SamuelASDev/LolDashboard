# Diário técnico e relatório de análise — LoL Dashboard

Documento de continuidade das sessões. Leia junto com `AGENTS.md` antes de modificar o projeto e acrescente uma entrada a cada sessão com alterações. Esta primeira entrada descreve o estado encontrado, não correções já implementadas.

## 2026-10-16 — Entrada 001: reconhecimento e revisão profissional

### O pedido e o ponto de partida

Hoje percorri a aplicação para entender o produto, a implementação e os riscos antes de recomendar mudanças. O sistema acompanha várias contas de League of Legends por usuário, guarda ranking e partidas Solo/Duo e apresenta indicadores por conta e por campeão. Há autenticação própria da aplicação e administração de usuários. Cadastrar uma conta Riot aqui significa cadastrar uma referência pública por Riot ID, não autenticar sua titularidade na Riot.

O checkout já tinha uma alteração de duas linhas no diff de `resources/views/dashboard.blade.php` (uma inserção e uma remoção). Ela foi preservada. O último commit encontrado foi `1637833` (`melhorias`); o histórico recente também tem `d9fba4a` e `c28bf78`. Não atribuo a estes commits decisões que não foram investigadas. Esta sessão acrescenta documentação e evidências de auditoria; não altera regras do aplicativo.

### Parecer executivo

A estrutura é coerente para um projeto pequeno: monólito Laravel, relações claras e interface renderizada no servidor. Existem boas proteções de autenticação e isolamento de contas. Porém, a confiabilidade da sincronização e a integridade dos indicadores precisam de trabalho antes de considerar o produto pronto para uma operação pública mais exigente.

As prioridades são restringir o destino das chamadas autenticadas à Riot, eliminar tentativas infinitas, distinguir falhas externas de respostas válidas e preservar a identidade e o período dos dados. O perfil está comprovadamente quebrado nas rotas. O frontend compila, mas a suíte não está integralmente verde. Auditorias de dependências retornaram alertas que precisam de triagem; a presença de um alerta não prova que o aplicativo expõe o caminho vulnerável.

### Como fiz a revisão e o que ela não comprova

Inspecionei código próprio em `app`, `routes`, `bootstrap`, `config`, migrations, seed/factory, testes e recursos de frontend, além de manifests, lockfiles e documentação. Revisei em detalhe os fluxos personalizados; os componentes e fluxos de autenticação herdados do Breeze foram conferidos quanto à ligação com o produto. Bibliotecas em `vendor` e `node_modules` foram avaliadas por manifests/auditorias e por trechos necessários para confirmar comportamento, não por leitura integral de código de terceiros.

Executei testes automatizados, build, listagem de rotas, lint PHP e compilação Blade. Não executei sincronização real, não consultei dados do banco de negócio, não inspecionei valores do `.env`, não fiz teste de carga nem navegação visual no browser. Falhas de isolamento e segurança apontadas por leitura devem receber testes dedicados; não houve exploração ativa contra servidores externos. Estado real de produção, e-mail, HTTPS, backups e infraestrutura permanece desconhecido.

Os caminhos e linhas abaixo são os do checkout desta entrada e podem mudar. “Confirmado por inspeção” significa que o comportamento está presente no código; não significa que houve exploração ou que o incidente já ocorreu.

### O mapa que construí do sistema

| Área | Arquivos principais | Responsabilidade e observações |
| --- | --- | --- |
| Inicialização | `bootstrap/app.php`, `bootstrap/providers.php`, `app/Providers/AppServiceProvider.php` | Registra rotas web/console, health `/up` e alias `admin`; provider próprio sem lógica adicional. |
| Rotas | `routes/web.php`, `routes/auth.php` | Home pública; dashboard/contas com `auth,verified`; administração com `admin`; autenticação Breeze. Há dois caminhos nomeados `contas.show` e faltam rotas de perfil. |
| Dashboard e contas | `app/Http/Controllers/ContaController.php` | Listagem, ordenação por tier/divisão/LP, CRUD, agregação por campeão e sincronização. Tem 488 linhas e concentra apresentação, rede e persistência. |
| Integração | `app/Services/RiotService.php` | Resolve Riot ID em PUUID, lê liga, lista IDs e detalhes; faz scraping de cutoff BR. Retornos `null`/`[]` ocultam a causa das falhas. |
| Administração | `AdminController`, `AdminMiddleware`, `resources/views/admin/index.blade.php` | Lista outros usuários, muda cargo para `user`/`mod`, exclui usuário com cascata. `mod` não tem permissão especial implementada. |
| Autenticação | `app/Http/Controllers/Auth/*`, `LoginRequest`, `routes/auth.php` | Registro, login/logout, recuperação, troca e confirmação de senha e verificação de e-mail. Login limita tentativas e regenera sessão. |
| Perfil | `ProfileController`, `ProfileUpdateRequest`, `resources/views/profile/*` | Implementação existe, mas sem rotas para edição, atualização e exclusão. |
| Persistência | `app/Models/{User,Conta,Partida}.php`, `database/migrations/*` | Usuário tem contas; conta tem partidas. Cascatas e unicidade composta protegem parte da integridade. |
| Interface própria | `layouts/main`, `welcome_custom`, `dashboard`, `contas/*`, `admin/index` | Tema escuro, cards, estatísticas, formulários POST/PUT/DELETE com CSRF; dashboard consulta banco dentro da view. |
| Interface herdada | `layouts/app`, `layouts/navigation`, `layouts/guest`, `components/*`, `auth/*`, `profile/*` | Convivem tema escuro e elementos claros do Breeze; navegação herdada referencia `profile.edit`, inexistente. |
| Assets | `resources/js/{app,bootstrap}.js`, `resources/css/app.css` | Alpine inicializado globalmente, Axios global com `X-Requested-With`; CSS usa diretivas Tailwind 3. |
| Ferramentas | `composer.json`, `package.json`, `vite.config.js`, `tailwind.config.js`, `postcss.config.js`, `phpunit.xml` | Vite gera assets, PostCSS aplica Tailwind; PHPUnit configurado para SQLite em memória. |
| Operação | `config/*`, `routes/console.php`, `database/seeders/DatabaseSeeder.php` | Configuração padrão Laravel; comando console de exemplo; seed cria usuário de teste. Não há job/scheduler próprio de sincronização nem pipeline CI versionado encontrado. |

### Dependências e ambiente observados

- Requisitos declarados: PHP `^8.2`, Laravel `^12.0`; Breeze e PHPUnit em desenvolvimento.
- Lock PHP: Laravel `v12.63.0`, Guzzle `7.14.0`, CommonMark `2.8.2`, Flysystem `3.35.2`, HttpFoundation `v7.4.14`.
- Executáveis locais: PHP CLI `8.2.12` (XAMPP) e Node `v24.20.0`. Build executado com Vite `7.3.6`.
- Frontend declarado: Alpine 3, Axios 1, Tailwind 3, PostCSS 8, Vite 7. `@tailwindcss/vite` 4 também está listado, mas não é usado pelo `vite.config.js`; não há evidência de falha de build causada por isso.
- `vendor` e `node_modules` estavam disponíveis. O `.env` existe e está ignorado pelo Git; `git ls-files` retornou somente `.env.example` entre esses dois arquivos. Isso não audita o histórico completo de segredos.
- `.env.example` não documenta `RIOT_KEY`; o serviço depende dela. O README continua sendo o README genérico do Laravel.

### A persistência e o significado dos dados

`users` armazena identidade de login, senha, e-mail e cargo. A migration do cargo define `user` como padrão. `User::$fillable` inclui `role`, mas o registro público usa uma lista explícita sem esse campo: não foi encontrada elevação de privilégio no registro atual. A inclusão continua sendo um ponto de atenção para futuros usos de mass assignment.

`contas` tem FK `user_id`, nick, Riot ID, e-mail opcional, região, elo, LP, vitórias, derrotas, winrate, cutoff e última partida. Não há PUUID persistido, identificação de temporada nem unicidade por conta Riot de um mesmo usuário. Campos de ranking podem ser nulos. Apenas `last_game_at` tem cast explícito no modelo.

`partidas` tem FK `conta_id`, match ID, campeão, resultado, K/D/A, posição e data. Tem casts de resultado/data. A última migration substitui match ID globalmente único por `(match_id, conta_id)`, permitindo registrar o desempenho de contas diferentes na mesma partida. Isso é adequado para estatísticas por jogador; uma soma global desses registros mede participações de contas, não necessariamente partidas distintas.

Há cascata `users -> contas -> partidas`. Isso evita órfãos, mas uma exclusão administrativa remove todo esse histórico. Não há soft delete nem trilha de auditoria de domínio implementada. A migration que reverte à unicidade global de `match_id` pode falhar caso já existam IDs compartilhados entre contas; precisa de estratégia de dados antes de rollback em ambiente com uso real.

### O percurso diário de um usuário

1. A home apresenta o produto e oferece login/registro. O registro cria usuário e autentica a sessão.
2. O dashboard busca contas do usuário e ordena tiers por `CASE`, depois divisões e LP. Soma vitórias/derrotas do ranking e consulta partidas persistidas para métricas por campeão.
3. Adicionar conta grava nick, Riot ID e região; não valida a existência na Riot naquele momento. Editar altera esses três campos sem revisar os dados derivados anteriores.
4. Sincronizar resolve PUUID, consulta liga Solo/Duo e, para Mestre+, última partida e cutoff. Lista IDs em páginas de 100 até o limite baseado em vitórias + derrotas + 60; insere apenas fila 420, ignorando entradas marcadas como early surrender.
5. A sincronização interrompe inserções quando a quantidade persistida alcança o total de jogos do ranking. Emite HTML de progresso e redireciona por JavaScript. “Atualizar todas” chama a mesma rotina sequencialmente e mantém cutoff temporário em sessão.
6. Os detalhes mostram agregados por campeão e métricas da conta. Embora carregue todas as partidas, a view não mostra uma listagem cronológica individual delas.
7. Admin vê outros usuários, altera `user`/`mod` ou exclui. A exclusão é definitiva; o texto “banir” não corresponde a uma suspensão persistente de acesso.

### Achados prioritários — registro aberto

Todos os achados abaixo estão **abertos** nesta entrada. P1 = tratar primeiro, por impacto de segurança, integridade ou disponibilidade; P2 = próximo ciclo de confiabilidade/funcionalidade; P3 = manutenção e qualidade. A prioridade considera este código, não uma exploração confirmada em produção.

#### A01 — P1 — Região sem lista permitida controla URL que recebe a chave Riot

**Evidência:** `ContaController::store/update` aceita `regiao` apenas como `required|string`; `RiotService.php:35-42` concatena esse valor em `https://{$regiao}.api.riotgames.com/...` e envia `api_key` na query.

**Impacto:** o campo não fica limitado às regiões do select. Uma string contendo um host e `/` pode deslocar o sufixo `.api.riotgames.com` para o caminho, permitindo direcionar a requisição a outro servidor e enviar a chave nele. Também há risco de requisições a destinos internos acessíveis pelo servidor. A execução depende de chegar à consulta de liga com PUUID resolvido. Confirmado por inspeção; sem disparar requisição maliciosa.

**Correção proposta:** enum/lista permitida no servidor e mapa de hosts constante no serviço; teste com entradas contendo `/`, `?`, `#` e destinos não permitidos, assegurando que nenhuma chamada externa é emitida. Chave em header reduz exposição em URLs, mas não substitui restringir o host.

#### A02 — P1 — Retentativa infinita pode ocupar o worker indefinidamente

**Evidência:** `ContaController.php:303-322` usa `while (!$matchData)` sem máximo. `getMatchDetail` retorna `null` para qualquer resposta HTTP sem sucesso; a rotina chama tudo de rate limit e dorme repetidamente. `set_time_limit(0)` remove o limite PHP da execução.

**Impacto:** 403 por chave inválida ou 404 permanente pode prender a sincronização; uma conta impede avançar nas seguintes. Interrupção pelo proxy continua possível, mas não representa cancelamento controlado da tarefa no servidor.

**Correção proposta:** limite por tentativas/tempo, distinguir 401/403/404/429/5xx, respeitar `Retry-After`, política de reagendamento e estado de falha. Há timeouts padrão de conexão/resposta no cliente Laravel instalado (10/30 segundos); o problema aqui é o tempo total e a política de retentativas, não ausência absoluta de timeout de rede.

#### A03 — P1 — Falha na consulta de ranking vira UNRANKED e deixa valores antigos

**Evidência:** `RiotService::getRankByPuuid` devolve `null` para falhas; o controller transforma o retorno em coleção e, sem entrada Solo/Duo, salva `UNRANKED` (`ContaController.php:385-389`). Nesse ramo não limpa wins, losses, LP, winrate, cutoff ou última partida.

**Impacto:** indisponibilidade externa altera o ranking para um estado falso; conta realmente sem ranking também pode exibir métricas de um ranking anterior. Os totais globais continuam somando valores antigos.

**Correção proposta:** retorno que diferencie sucesso sem ranking de erro; preservar último snapshot válido em falha e exibir data/estado da sincronização. Definir limpeza consistente para resposta válida sem ranking.

#### A04 — P1 — Contagem acumulada não é um cursor válido de sincronização

**Evidência:** `ContaController.php:246-295` pagina conforme total de jogos da liga e usa contagem de todas as partidas da conta para parar; não há temporada/split no schema nem filtro temporal nas consultas de IDs.

**Impacto:** após reset, 300 registros antigos com ranking atual de 5 jogos já satisfazem a condição de parada; os jogos novos deixam de entrar. Lacunas podem ser preenchidas por partidas de outro período. Igualar contagens não comprova que os conjuntos são iguais. O filtro `gameEndedInEarlySurrender` também merece validação: o código pressupõe que todo resultado assim marcado é remake e não participa do total da liga, sem comprovação dessa equivalência.

**Correção proposta:** definir período/fila e chave de identidade, importar incrementalmente por IDs/data, registrar cobertura e cursor e aceitar que o histórico acessível pode não coincidir com o contador da liga. Testar reset de temporada, lacunas, partidas novas, early surrender e histórico parcialmente disponível.

#### A05 — P1 — Editar identidade mantém ranking e partidas da conta anterior

**Evidência:** `ContaController.php:462-476` permite trocar Riot ID/região sem revisar partidas, elo ou metadados. Não há PUUID salvo para distinguir troca de nome da mesma conta de troca para outra conta.

**Impacto:** a página pode combinar jogos de jogadores diferentes. A condição de parada de A04 ainda pode impedir a importação correta da nova conta.

**Correção proposta:** persistir PUUID, separar apelido de identidade estável e definir fluxo explícito de mudança de identidade. Preservar histórico com vínculo correto ou solicitar decisão de retenção antes de limpar dados.

#### A06 — P1 — Saída HTML sem escaping e erros técnicos expostos

**Evidência:** `ContaController.php:274,376,381,388,436` imprime Riot ID diretamente em HTML; `syncAll` imprime antes de validar na Riot. O catch também imprime `getMessage()` da exceção. Há outros dados externos interpolados no console.

**Impacto:** HTML/script salvo no Riot ID pode executar no console de sincronização do próprio usuário. O isolamento atual limita o alcance observado à conta de aplicação que gravou o dado; não foi demonstrado ataque entre usuários. Exceções de rede/banco podem revelar URLs com chave, nomes de tabelas e detalhes internos.

**Correção proposta:** view/stream estruturado com escaping ou progresso como texto/JSON; mensagens públicas controladas, logs sanitizados e teste com caracteres HTML. Não exibir mensagem bruta de exceção.

#### A07 — P1 — Dependências com alertas nas auditorias desta data

**Evidência:** `npm audit` retornou **15 pacotes sinalizados**, com severidades agregadas: **1 critical, 11 high e 3 moderate**. Isso é contagem de pacotes na estrutura `vulnerabilities`, não contagem de CVEs distintos. O pacote crítico é `shell-quote`, transitivo. Diretos sinalizados: Axios, concurrently, PostCSS e TailwindCSS.

`composer audit` retornou **22 avisos em 6 pacotes**: Guzzle (6), Laravel (1), CommonMark (12), Flysystem (1), HttpFoundation (1) e Symfony YAML (1). Nenhum pacote abandonado foi reportado. Evidências completas, incluindo títulos, intervalos e links, estão em [auditoria npm](auditorias/2026-10-16-npm-audit.json) e [auditoria Composer](auditorias/2026-10-16-composer-audit.json).

**Impacto e limites:** há dependências de build/desenvolvimento e de runtime. Não equiparar severidade do banco de avisos com exposição comprovada do produto; a triagem deve considerar versão instalada, recurso utilizado e entradas controláveis. Axios, embora listado em `devDependencies`, é importado e incorporado no bundle do frontend.

**Correção proposta:** revisar os avisos, selecionar versões corrigidas compatíveis, atualizar lockfiles de forma controlada e repetir testes/build/auditorias. Não foi aplicado `audit fix`, atualização de versões ou troca de major nesta sessão.

#### A08 — P2 — Perfil sem rotas: cinco testes falham

**Evidência:** `route:list` não contém `/profile`; `ProfileController` e views existem. `tests/Feature/ProfileTest.php` apresentou cinco falhas. Navegação herdada e formulários chamam `profile.edit/update/destroy`.

**Impacto:** editar perfil e excluir a própria conta não estão acessíveis; renderizar a navegação herdada também pode lançar erro de rota ausente. O dashboard usa outro layout, portanto esse erro não necessariamente ocorre nele.

**Correção proposta:** registrar as três rotas com autenticação e integrar o acesso ao layout usado, ou formalizar remoção do recurso e ajustar referências/testes. Restaurar o recurso existente é o caminho de menor mudança funcional.

#### A09 — P2 — Middleware verified sem obrigação de verificar e-mail

**Evidência:** `routes/web.php` aplica `verified`, mas `User` não implementa `MustVerifyEmail`. O middleware instalado exige essa interface para bloquear usuário não verificado.

**Impacto:** a presença do middleware sugere uma proteção que não está efetiva. Os testes atuais de verificação não testam bloqueio do dashboard para usuário não verificado.

**Correção proposta:** decidir se confirmação é obrigatória; implementar o contrato e entrega de e-mail com testes, ou remover a expectativa e ajustar UX. O relatório não presume que obrigatoriedade já foi decidida pelo produto.

#### A10 — P2 — Formulário oferece regiões incompatíveis com Match-V5 fixo em Americas

**Evidência:** formulários oferecem BR1, NA1, EUW1, EUN1, KR e JP1. As chamadas Match-V5 do serviço usam sempre `americas.api.riotgames.com`; apenas League-V4 recebe plataforma dinâmica. Cutoff também está fixo em BR.

**Impacto:** histórico de Europa/Ásia não é roteado corretamente. Mesmo para outras plataformas, dados de corte BR são apresentados como referência geral. A documentação oficial distingue roteamento por plataforma e região: [Riot — Routing Values](https://developer.riotgames.com/docs/lol#routing-values).

**Correção proposta:** mapa explícito de plataforma para rota regional, passado a todos os métodos de histórico; cutoff por região com origem e validade. Confirmar separadamente o contrato de Account-V1 ao alterar a resolução de contas.

#### A11 — P2 — Chave lida fora de config e identificadores sem encoding de segmento

**Evidência:** `RiotService.php:14` usa `env('RIOT_KEY')` diretamente, sem entrada em `config/services.php`; `getAccount` interpola nome/tag no caminho sem `rawurlencode` de cada segmento.

**Impacto:** com configuração em cache e chave disponível somente no `.env`, a integração pode perder acesso à chave. Caracteres especiais do Riot ID podem mudar a interpretação da URL. A condição do cache foi conferida na [documentação Laravel](https://laravel.com/docs/12.x/configuration#configuration-caching); ambiente externo ainda pode fornecer a variável, portanto a falha não é universal.

**Correção proposta:** `config('services.riot.key')`, variável documentada sem valor real e codificação de cada segmento. Testar nomes com espaços e caracteres reservados usando HTTP fake.

#### A12 — P2 — Trabalho longo dentro de requisição e ausência de trava por conta

**Evidência:** controller usa `sleep/usleep`, flush manual, headers diretos, redirecionamento JS e sincronização sequencial; não há lock distribuído ou job de domínio. A chave de sessão de cutoff não é uma trava de sincronização. A restrição composta/`firstOrCreate` evita duplicidade de linha, mas não duplica capacidade nem coordena chamadas de rede.

**Impacto:** múltiplos cliques/abas podem repetir trabalho e disputar limite compartilhado da chave. Workers ficam ocupados por minutos, erros após início de saída podem ter status/resposta confusos e proxies podem bufferizar o console. A limpeza da sessão em `syncAll` não está em `finally`; uma exceção fora do catch pode impedir a limpeza.

**Correção proposta:** job por conta com exclusão mútua, estado persistido, tentativas limitadas, progresso consultável e limite global de API. Manter uma alternativa síncrona limitada pode ser suficiente para uso pequeno; a necessidade operacional deve orientar a mudança.

#### A13 — P2 — Persistência parcial e mensagens de sucesso pouco confiáveis

**Evidência:** partidas são inseridas durante o loop, mas snapshot de ranking só é salvo no fim do bloco; exceções são capturadas e apenas impressas. Lista de IDs retorna `[]` tanto para erro quanto para ausência. Requisições de conta/liga/cutoff ocorrem fora do catch do histórico. A divisão de winrate não protege contra total zero. Há acesso direto a chaves do payload.

**Impacto:** histórico parcialmente salvo com ranking antigo, conclusão aparente após falhas e exceções não padronizadas. Alguns erros PHP não são subclasses de `Exception`, portanto o catch não cobre todos os casos.

**Correção proposta:** resultados explícitos por etapa, validar estrutura do payload, tratar zero jogos, separar snapshot e importação com estados consistentes. Usar transações curtas para gravações relacionadas; não manter transação aberta durante espera/rede.

#### A14 — P2 — Estimativas de decay e corte exibidas com precisão excessiva

**Evidência:** `dashboard.blade.php:48-64,129-158` calcula corte Challenger como GM + 400 LP e reserva como 336 horas menos tempo desde a última partida; não guarda banco real de dias nem eventos que o determinam. A home promete alerta “preciso”. `getGrandmasterCutoff` captura a primeira ocorrência de número seguido de LP no HTML, sem validar status, contexto ou idade.

**Impacto:** os dados disponíveis no modelo não bastam para garantir a reserva real; uma regra fixa pode transmitir falsa segurança. O scraper pode pegar outro valor ou falhar. O fallback 600 LP não é medição atual. Não foram validadas regras vigentes de decay para cada região/temporada.

**Correção proposta:** rotular estimativa e sua origem/data, documentar limites do modelo, validar regras oficiais vigentes antes de implementar fórmula e obter dados apropriados quando disponíveis. Retirar a precisão prometida até haver evidência.

#### A15 — P2 — Consultas em view, carga integral e agregações repetidas

**Evidência:** `ContaController::index/show` carrega todas as partidas em memória; `dashboard.blade.php:168` consulta novamente todas as partidas para cada conta. Administração lista todos os usuários com `get()`. A view ordena todo o histórico e usa esse conjunto como “Recentes”, sem janela temporal.

**Impacto:** padrão N+1 por conta, reprocessamento dos mesmos registros e crescimento de memória/tempo com o histórico. Não há medição de carga nesta revisão. Não há índice composto específico `(conta_id, played_at)` declarado nas migrations.

**Correção proposta:** mover agregações para serviço/query, agrupar no banco, buscar só campos necessários, definir janela de recentes e paginação onde útil. Avaliar índices por plano de consulta antes de adicionar indiscriminadamente. O GM cutoff calculado no controller não é consumido pela view atual, podendo causar chamada externa no GET sem benefício visível.

#### A16 — P2 — Administração protege entrada, mas não invariantes das operações

**Evidência:** middleware exige `role === 'admin'`; a lista exclui o admin atual, mas `updateRole` e `destroy` aceitam qualquer ID existente. Não impedem operar sobre si mesmo/outros administradores ou remover o último admin. `mod` não tem rota/permissão distinta; exclusão é chamada de banimento.

**Impacto:** requisição direta de um admin pode deixar o sistema sem administrador. Exclusão não impede recadastro e remove dados permanentemente. Não há auditoria de quem mudou cargo/excluiu usuário.

**Correção proposta:** definir política de administração/último admin e semântica de moderador/banimento; aplicar no servidor, com testes e registro de ações. Não foi encontrada capacidade de um usuário comum passar pelo middleware atual.

#### A17 — P2 — Validação de negócio incompleta e duplicatas possíveis

**Evidência:** nick/Riot ID/região sem comprimento máximo, formato ou normalização; schema usa strings de tamanho limitado. Não há restrição para cadastro repetido da mesma conta por usuário. Formulários próprios não mostram erros de validação e não reaplicam `old()`; na edição voltam ao valor persistido.

**Impacto:** falha de banco em entradas grandes (dependente do driver), cadastros inúteis, duplicação de chamadas e métricas e usuário sem explicação ao falhar.

**Correção proposta:** Form Requests com regras compartilhadas, max coerente com schema, Riot ID validado, região permitida, identidade canônica e erros junto aos campos. Definir se duplicatas são proibidas por produto antes de migration única.

#### A18 — P3 — Nome de rota duplicado

**Evidência:** `Route::resource` registra `contas.show` e uma rota adicional `/contas/{id}/detalhes` reutiliza o mesmo nome.

**Impacto:** geração de URLs ambígua e possível rejeição ao compilar cache de rotas. `route:list` confirmou a duplicidade; não foi executado `route:cache` sobre o ambiente atual para provar o segundo efeito.

**Correção proposta:** escolher rota canônica e dar nome distinto à alternativa, ou manter redirect compatível sem duplicar nome. Verificar cache de rotas em ambiente isolado.

#### A19 — P3 — UX, acessibilidade e identidade visual precisam de revisão

**Evidência estática:** sidebar fixa `w-64` em container horizontal sem alternativa mobile em `layouts/main`; inputs próprios com labels sem `for/id`; botão de sincronização só com emoji/title; classes `slate-850` usadas sem cor definida no Tailwind; textos misturam português/inglês; componentes claros convivem com fundo escuro; guest layout limita overflow vertical. Imagens dependem de CDN externa e escondem o elemento ao falhar.

**Impacto:** risco de espaço insuficiente/rolagem em telas pequenas, associação de campos inadequada para leitores de tela, contraste irregular e falha silenciosa de imagens. São observações de código; não houve avaliação visual nem medição formal de acessibilidade. URLs de imagem não foram validadas ao vivo.

**Correção proposta:** navegar em larguras pequenas e desktop, teclado e leitor de tela; associar labels, adicionar nomes acessíveis, usar cores válidas, estados de carregamento/erro e fallback de imagem. Centralizar resolução de assets de campeões por identidade estável, em vez de pressupor que remover espaços do nome cobre todos os casos.

#### A20 — P3 — Documentação, cobertura e operação ainda são de esqueleto

**Evidência:** README genérico; testes existentes cobrem principalmente Breeze, sem testes de domínio para contas, isolamento, admin, ranking, API e sincronização; testes Example apenas validam home/verdadeiro. Sem CI versionada encontrada. Seed cria usuário de teste e factory usa senha conhecida de desenvolvimento. Scripts `setup` incluem migrations forçadas e geração de chave; `dev` inclui Pail, cuja adequação ao Windows deve ser verificada.

**Impacto:** novos colaboradores não têm procedimento do produto, mudanças centrais podem quebrar sem detecção e scripts de bootstrap são perigosos se executados indiscriminadamente sobre instalação existente. A seed não cria admin; não há procedimento documentado de bootstrap administrativo.

**Correção proposta:** README de instalação limpa/operação, configuração sem segredos, política de admin, worker/scheduler quando existirem, testes de domínio e CI proporcional. Rever mail, cookies/HTTPS, cache, logs sem segredos, backups e restauração no ambiente real. Não foi presumida configuração insegura em produção a partir do exemplo de desenvolvimento.

### O que está bem encaminhado

- Contas são obtidas pela relação do usuário autenticado em listagem, detalhes, edição, exclusão e sincronização. Isso reduz exposição a acesso por ID de outro usuário; faltam testes para preservar a garantia.
- Auth Breeze usa hash de senha, regeneração/invalidação de sessão, tokens de reset e limitação de tentativas no login. Os testes executados desses fluxos passaram.
- Formulários de alteração inspecionados incluem CSRF e métodos HTTP apropriados. Blade usa escaping nas páginas principais; a exceção importante é o console HTML do controller.
- FKs com cascata e unicidade composta expressam relações reais; `firstOrCreate` junto à restrição ajuda a impedir partidas duplicadas por conta.
- A ordenação de ranking usa expressões SQL constantes, sem entrada de usuário interpolada nesses `CASE`; não foi identificada SQL injection nessas expressões.
- Vite/Tailwind geram os assets com sucesso. Não há necessidade demonstrada de reescrever a aplicação em SPA ou trocar framework para resolver os achados.

### As verificações que executei

| Verificação | Resultado observado | Limite da conclusão |
| --- | --- | --- |
| `php artisan test` | **20 passaram, 5 falharam; 48 assertions; 12,45 s** | Cinco falhas em `ProfileTest`; não existe cobertura adequada do domínio Riot/contas. |
| `npm.cmd run build` | **Passou**, 56 módulos; CSS 60,05 kB e JS 92,32 kB antes de gzip | Compilar não valida aparência, rede ou comportamento de negócio. |
| `php -l` nos arquivos PHP de app/bootstrap/config/database/routes/tests | **60 arquivos passaram** | Não é análise de tipos nem execução de todos os caminhos. |
| `php artisan route:list --except-vendor` | **29 rotas listadas** | Confirma ausência do perfil e duplicidade de nome; health/vendor foram filtrados. |
| `php artisan view:cache` e `view:clear` | **Passaram** | Compilação Blade não renderiza com dados nem valida `route()` em runtime. |
| `npm.cmd audit --json` | **15 pacotes sinalizados: 1 critical, 11 high, 3 moderate** | Snapshot online desta data; não demonstra explorabilidade. |
| `composer.bat audit --format=json` | **22 avisos em 6 pacotes**, saída 1 | Não houve atualização nem análise exaustiva de exposição por aviso. |
| `git status --short` inicial | Apenas dashboard modificado | Alteração preexistente preservada. |

As falhas de perfil foram: exibir página (404), atualizar informações (404), manter verificação ao não mudar e-mail (404), excluir conta (404) e exigir senha correta para exclusão (bag de erros ausente porque a rota não existe).

Build criou arquivos ignorados em `public/build`. Testes e compilação podem atualizar caches/logs locais; o cache de views foi limpo ao final. Nenhuma migration foi executada contra o banco do usuário. As auditorias fizeram consultas de metadados aos registros de dependências; não usaram a chave Riot.

### Como eu organizaria o próximo trabalho

1. **Conter riscos diretos:** A01 (destinos permitidos), A02 (retentativas limitadas), A03 (erro versus sem ranking), A06 (saída segura) e triagem A07. Testar HTTP sem rede real.
2. **Recuperar base verificável:** A08 e teste de bloqueio/decisão de verificação de e-mail A09; adicionar testes de isolamento/admin e validação. Registrar baseline verde sem remover testes úteis.
3. **Consertar identidade e histórico:** A04/A05, PUUID e períodos, dados derivados consistentes, roteamento A10 e configuração A11. Planejar migration e tratamento de dados existentes antes de executar.
4. **Dar controle à sincronização:** A12/A13, serviço de aplicação, estado persistido, trava e política de API; jobs conforme necessidade do ambiente.
5. **Melhorar leitura e uso:** A14/A15, definir métricas e estimativas, eliminar consultas em views, depois A16–A20, revisão mobile e documentação operacional.

Essa é uma ordem sugerida, não autorização para excluir dados, reescrever a arquitetura ou executar atualizações automáticas em sessões futuras.

### Cenários de aceitação a construir

- Usuário A recebe 404/negação ao tentar ler, editar, excluir ou sincronizar conta de B; `syncAll` de A não toca B.
- Região fora da lista é rejeitada antes de qualquer chamada; destino e chave nunca aparecem em erro público.
- 403/404 encerram de modo controlado; 429 respeita espera com limite; 5xx/rede não zera ranking; zero jogos não divide por zero.
- Duas sincronizações da mesma conta não repetem importação; a mesma partida de contas distintas continua permitida.
- Temporada nova com histórico antigo importa jogos novos; execução interrompida pode continuar sem perder consistência.
- Renomear o mesmo PUUID preserva histórico; trocar identidade não mistura jogadores.
- Payload incompleto, caracteres especiais no Riot ID e conteúdo HTML são tratados sem execução de script nem erro técnico exposto.
- `/profile` funciona com autenticação e senha exigida na exclusão; administração preserva as invariantes decididas para cargos.
- Dashboard vazio e populado renderizam sem depender de chamada externa; recentes usam período explícito e estimativas têm origem/data.

### Fontes técnicas usadas para confirmar pontos específicos

- [Riot Developer Portal — League of Legends / Routing Values](https://developer.riotgames.com/docs/lol#routing-values): diferencia rotas de plataforma e regionais. Consultado em 2026-10-16.
- [Laravel 12 — Configuration Caching](https://laravel.com/docs/12.x/configuration#configuration-caching): comportamento de `env()` com configuração em cache. Consultado em 2026-10-16.
- Código instalado: `EnsureEmailIsVerified` confirma checagem do contrato; `PendingRequest` confirma timeouts padrão. As versões locais importam para essas conclusões.
- JSONs das auditorias anexados nesta pasta: evidências de dependências com links individuais para avisos. São snapshots históricos, não um painel continuamente atualizado.

### Fechamento da sessão e passagem de contexto

**Entregas:** `AGENTS.md`, este diário e os dois JSONs de auditoria. `AGENTS.md` exige leitura inicial e atualização do relatório a cada modificação, incluindo documentação, com preservação de histórico e evidências.

**Estado:** A01–A20 abertos. Não há correção funcional implementada nesta sessão. A alteração anterior do dashboard permanece intocada. Os resultados de teste são o baseline desta revisão, não uma promessa de que todas as funções foram exercitadas.

**Próximo passo recomendado:** começar por A01/A02/A03/A06 com testes de integração simulada, enquanto se faz triagem dos avisos A07. Registrar cada conjunto de mudanças em nova entrada abaixo e atualizar explicitamente o status dos achados tratados.

## Entradas seguintes

Acrescente novas entradas cronológicas aqui, usando o modelo de `AGENTS.md`. Preserve a Entrada 001 como retrato inicial e registre correções e reavaliações com referência aos IDs.

### 2026-10-16 — Entrada 002: preparação da branch dev

- Objetivo: trabalhar na branch `dev` com todo o conteúdo atualizado da `main`, conforme pedido do usuário.
- Alterações e justificativa: executado `git fetch origin`; não havia branch `dev` local nem remota após a consulta. Criada `dev` a partir de `main` e executado `git merge --ff-only origin/main`, que informou que já estava atualizada. Ambas apontam para `1637833` nesta sessão.
- Arquivos afetados: este diário recebeu o registro. A alteração preexistente em `resources/views/dashboard.blade.php`, o `AGENTS.md` e a pasta `docs/` foram preservados no diretório de trabalho, ainda sem commit.
- Verificações executadas e resultados: branch ativa confirmada como `dev`; comparação `dev...origin/main` retornou zero commits exclusivos em ambos os lados; estado local conferido sem conflitos.
- Achados atualizados: A01–A20 permanecem abertos; nenhuma correção funcional nesta sessão.
- Limitações e pendências: não foi feito commit nem push; `dev` existe localmente. Testes/build não foram repetidos porque não houve alteração funcional ou incorporação de commits novos; permanece o baseline anterior de 20 testes passando e 5 falhando no perfil.
- Próximo passo recomendado: realizar as próximas alterações na `dev` e manter este diário atualizado.

### 2026-10-16 — Entrada 003: refatoração modular, segurança e operação

**Objetivo e autorização.** O usuário autorizou implementar todos os achados e refatorar amplamente o software, com liberdade de reorganização. O trabalho ocorreu na branch local `dev`. Não houve commit, push ou publicação.

**Decisões de arquitetura.** Mantive o monólito Laravel/Blade, agora com controllers menores, Form Request compartilhado, enum de região, policy de contas, casos de uso em `app/Actions`, queries em `app/Queries` e cliente Riot como fronteira de integração. Não introduzi repositórios que apenas duplicariam Eloquent. `SyncAccount` processa unidades curtas na fila e `sync_runs` armazena a máquina de estados. Leia [arquitetura](ARQUITETURA.md) e [operação](OPERACAO.md) para o estado atual; a Entrada 001 continua sendo o retrato anterior.

**Alterações por conjunto lógico e justificativa.**

1. **Fronteira Riot:** `RiotRegion` e `config/riot.php` restringem plataformas/hosts e mapeiam Americas/Europe/Asia. Identificadores são codificados por segmento. Chave lida por `config()`, enviada em header; redirects recusados. Status HTTP, conteúdo e payloads recebem validação explícita. Erros não se confundem com conta sem ranking. Orçamento de API compartilhado por cache e lock cobre janelas de 1/120 segundos. Sem retentativas de rede infinitas nem mensagens técnicas impressas.
2. **Sincronização em fila:** `StartSync`, `AdvanceSync`, `ImportMatch` e `SyncAccount` substituem streaming HTML e pausas no controller. Versão, lock e chave única de conta ativa evitam trabalho concorrente. Cursor, pendências, contadores e próximo horário são persistidos; partida/checkpoint são atômicos. Retry finito, timeout, prazo total, cancelamento e `riot:recover` com scheduler cobrem interrupções. A recuperação respeita `Retry-After` persistido e não reseta o cursor.
3. **Identidade e integridade:** PUUID e fingerprint persistidos; unicidade por dono/região/PUUID e identidade de novos cadastros. Cadastro repetido recebe validação. Identidade confirmada não pode ser trocada pelo formulário; renomeações são obtidas por PUUID. Não se usa contagem da liga para parar importação. Jogos confirmados são pulados em lote, mas páginas novas continuam sendo percorridas. Ranking válido sem liga Solo/Duo limpa métricas anteriores; erro conserva o snapshot.
4. **Legado sem perda:** migration aditiva adiciona campos, índices, `sync_runs` e `audit_events`. Registros antigos sem PUUID não são assumidos como corretos: ficam preservados, fora dos agregados, e podem ser revalidados pela API. Não houve exclusão ou reatribuição em massa de partidas. A proteção do rollback antigo recusa unicidade global se houver IDs compartilhados.
5. **Leitura e desempenho:** agregações por SQL em `AccountStatistics` e ordenação em `AccountRanking`. Não há consulta de partidas dentro de Blade. Filtro móvel de 7/30/90/365 dias ou todo o histórico confirmado; histórico individual e administração paginados. Índice `(conta_id, played_at)`. Métrica global rotulada como participações para não confundir duas contas no mesmo jogo com partidas distintas.
6. **Acesso e administração:** perfil restaurado e integrado; `MustVerifyEmail` torna verificação efetiva. Cargo saiu do mass assignment. Moderador é consulta; admin pode gerenciar user/mod. Operações protegem outros admins, o próprio admin e o último administrador no autosserviço. Exclusão não é chamada de banimento. Auditoria registra mutações; promoção administrativa é comando de console para usuário existente. Seed deixou de criar credenciais conhecidas.
7. **Apresentação:** dashboard, home, contas, administração, perfil e guest layout revisados; componentes de filtro, resumo e tabela compartilhados. Tema escuro consistente, português, labels, foco visível, link de salto, navegação mobile com estado inicial correto, feedback de erros e dados anteriores no formulário. Progresso usa Fetch/Alpine e texto escapado, com polling limitado, cancelamento e descarte de timers. Sem dependência de CDN de retratos que pudesse falhar silenciosamente. Reserva de decay e cortes presumidos foram retirados; última atividade e limites dos dados ficam claros.
8. **Dependências e tooling:** atualização compatível de Laravel 12 e dependências PHP; migração controlada Tailwind 3 -> 4 por plugin Vite para eliminar a cadeia com avisos. Removidos Axios, concurrently, plugin/configuração redundantes de PostCSS/Tailwind e import global sem uso. Runner de desenvolvimento PHP/Symfony Process inicia web, worker, scheduler e Vite. README específico e CI com PHP/JS/browser, Pint, build, cache de rotas e auditorias.
9. **Segurança de resposta:** headers nosniff, anti-enquadramento, referrer e cache privado/no-store em respostas autenticadas. Logs inesperados da sincronização registram classe e run ID, sem conteúdo da exceção ou chave.

**Arquivos afetados.** Novos módulos em `app/Actions`, `app/Queries`, `app/Enums`, `app/Policies`, `app/Exceptions`, `app/Jobs` e `app/Console/Commands`; models/requests/middleware/controllers e rotas ajustados; nova migration e proteção de down da migration de unicidade antiga; views, CSS/JS e manifests/lockfiles; suites PHP/JS/browser; `scripts/dev.php`, `playwright.config.js`, `.github/workflows/ci.yml`, `.env.example`, README, guias de arquitetura/operação e AGENTS. Pint normalizou o estilo PHP, inclusive pequenos ajustes de formatação em migrations antigas, sem mudar seus métodos up.

**Alteração local anterior do usuário.** A fórmula de dias restantes do dashboard já estava modificada antes desta tarefa. Com a autorização para a refatoração ampla, o contador foi retirado junto da previsão de decay: sem banco real de dias, a fórmula não sustentava a precisão exibida. O histórico da decisão permanece nas Entradas 001/002 e em ARQUITETURA.md; última atividade continua disponível. O arquivo não foi simplesmente restaurado à versão antiga.

**Migration local.** `migrate:status` confirmou que só a migration nova estava pendente. Executei exclusivamente `2026_10_16_000001_add_sync_identity_and_auditing` com migrate --path e force para instalar a nova versão local. Ela terminou com sucesso, batch 2; as anteriores continuaram batch 1. Não houve migrate:fresh, seed, rollback ou reset no banco do usuário. Testes de upgrade/rollback foram executados exclusivamente em SQLite em memória; a fixture do browser usa outro banco isolado em storage/framework/testing.

**Verificação final observada.**

| Verificação | Resultado |
| --- | --- |
| `php artisan test --compact` | **65 testes passaram, 224 assertions**, aproximadamente 4 s na última execução |
| `npm.cmd test` | **3 testes JavaScript passaram**, incluindo encerramento do polling e descarte de tarefas |
| `npm.cmd run test:browser` | **4 cenários passaram**, desktop e mobile, com Chrome instalado e banco isolado; nenhuma exceção JavaScript nos fluxos verificados |
| Renderização visual | Capturas desktop/mobile inspecionadas; corrigida visibilidade inicial do menu mobile e conferido layout sem overflow horizontal da página |
| `npm.cmd run build` | **Passou**, Vite 7.3.7; CSS 48,52 kB e JS 56,28 kB sem gzip |
| `php vendor/bin/pint --test` | **Passou** após normalização do estilo |
| `composer validate --strict` | **Passou** após atualizar metadata do lockfile |
| Cache de rotas e Blade | Ambos gerados com sucesso e limpos após a verificação |
| Migration local | **Aplicada**, única migration pendente, sem reset |
| Auditoria Composer | **Zero avisos**, snapshot pós-refatoração anexado |
| Auditoria npm | **Zero vulnerabilidades**, snapshot pós-refatoração anexado |

Snapshots atuais: [Composer](auditorias/2026-10-16-pos-refatoracao-composer.json), [npm](auditorias/2026-10-16-pos-refatoracao-npm.json). Os snapshots anteriores com alertas foram preservados como evidência histórica.

**Cobertura acrescentada.** Isolamento de usuário em leitura/edição/exclusão/sync/status/cancelamento; login e verificação de e-mail; validação/duplicatas e campos privilegiados; filtros de data/legado/identidade/remake; paginação; escaping; regiões e encoding; chave em header; 403/404/429/500; limite local; payload malformado e zero jogos; histórico antigo maior que contador novo; falha preservando ranking; UNRANKED válido; tentativas finitas/cursor; jobs antigos/cancelados; revalidação de legado; partidas compartilhadas; early surrender/remake; recuperação e lock; Retry-After; execução serializada por worker database; user/mod/admin e último admin; migrations aditivas e rollback sem perda. Testes impedem HTTP não simulado.

**Situação dos achados da Entrada 001.**

| ID | Estado nesta entrega | Evidência/decisão |
| --- | --- | --- |
| A01 | Corrigido | Enum/hosts fixos, validação antes de HTTP, teste de região maliciosa |
| A02 | Corrigido | Jobs limitados, retry finito, sem loop infinito; testes de falha permanente/transitória |
| A03 | Corrigido | Erro preserva snapshot; resposta válida sem liga zera ranking; testes específicos |
| A04 | Corrigido no algoritmo | Paginação independente da contagem, filtro por datas e identidade; teste com histórico antigo excedendo total atual. Não promete reconstruir temporada oficial |
| A05 | Corrigido | PUUID persistido, identidade imutável no cadastro confirmado, renomeação por API; legado revalidado sem exclusão |
| A06 | Corrigido | Console bruto removido, Blade/x-text, mensagens seguras, teste de escaping e ausência de chave na URL |
| A07 | Corrigido nesta data | Auditorias atuais zeradas; lockfiles atualizados e build/testes passando |
| A08 | Corrigido | Rotas/perfil restaurados; cinco testes antigos passam |
| A09 | Corrigido | Contrato de verificação efetivo e teste do bloqueio do dashboard |
| A10 | Corrigido | Mapeamento de todas as plataformas suportadas; testes das seis regiões |
| A11 | Corrigido | Config de Riot e encoding por segmento; teste de caracteres especiais e chave ausente |
| A12 | Corrigido | Fila, status, cancelamento, lock, versão, orçamento compartilhado e recuperação |
| A13 | Corrigido no código | Snapshot independente, checkpoint atômico, validação e falhas controladas; testes de preservação e payload |
| A14 | Tratado por remoção de previsão sem base | Scraper, fallback 600, fórmula GM+400 e promessa de reserva exata removidos; não há novo cálculo de decay para homologar |
| A15 | Corrigido estruturalmente | Agregações SQL, sem queries em view, paginação/índice e período explícito; carga de produção ainda não medida |
| A16 | Corrigido | Ações protegidas, mod consulta, exclusão explícita, auditoria e bootstrap seguro; testes de permissão/invariantes |
| A17 | Corrigido | Form Request, limites, lista permitida, duplicatas, feedback e retenção de entrada; testes PHP/browser |
| A18 | Corrigido | Nome canônico único, redirect legado separado; route:cache passou |
| A19 | Tratado e verificado nos fluxos principais | Tema, responsividade, formulários e foco revisados; browser desktop/mobile e screenshots. Não equivale a certificação formal de acessibilidade |
| A20 | Implementado no repositório | Documentação, comandos, CI e novas suites. CI remota, e-mail, backup/restauração e infraestrutura real ainda exigem operação/homologação |

**Limites que permanecem explícitos.** Não houve chamada autenticada à Riot real, envio real de e-mail, teste de carga, restauração de backup ou deploy. O CI foi escrito, mas não acionado no GitHub porque não houve push. Profundidade histórica depende da API. Remake usa heurística flag + menos de 300 s; não deve ser confundido com contrato oficial de contabilização da liga. Usuários antigos precisam confirmar e-mail; configure o mailer para isso. A fila/scheduler devem estar ativos para importar/recuperar tarefas. Registros legados fora de alcance da API continuam preservados e não entram nas métricas.

**Próximo passo operacional.** Iniciar com `composer dev` no ambiente local, conferir a entrega de e-mail configurada e homologar uma conta com chave Riot válida. Para produção, seguir OPERACAO.md e validar carga/backups em ambiente próprio. Novas modificações continuam obrigadas a atualizar este diário.


### 2026-10-16 — Entrada 004: confirmação de e-mail para teste local

- Objetivo: liberar o acesso do usuário existente ao painel local, conforme solicitação explícita.
- Alterações e justificativa: confirmado APP_ENV=local e identificado um único usuário cadastrado (ID 1). Seu e-mail foi marcado como verificado pelo método do modelo, sem alterar senha, cargo ou a exigência de verificação do aplicativo.
- Dados afetados: email_verified_at do usuário ID 1 e evento user.email_verified_local_test em audit_events. Nenhum e-mail pessoal ou credencial foi incluído neste registro versionado.
- Verificações executadas e resultados: usuário relido do banco com hasVerifiedEmail() verdadeiro; cargo user preservado; operação protegida por checagem de ambiente e executada em transação.
- Achados atualizados: A09 continua corrigido. Esta é uma exceção de dados expressamente autorizada para testes locais, sem bypass no código.
- Limitações e pendências: não houve envio/validação de e-mail real. Testes e build não foram repetidos, pois não houve alteração funcional no código.
- Próximo passo recomendado: atualizar o navegador e acessar o dashboard com o usuário existente.

### 2026-10-16 — Entrada 005: histórico completo de partidas e estatísticas pessoais

- Objetivo: implementar recursos inspirados no perfil do LeagueOfGraphs indicado, com jogadores/campeões, barra de partidas e análises pessoais, evitando dependência de médias de uma base externa.
- Persistência/importação: criada migration aditiva 000002, RiotMatch, MatchParticipant e RankSnapshot; Partida ganhou campos anuláveis de duração, farm, ouro, dano, visão, sentinelas, participação, adversário, cura, pentakills, build, feitiços e runas. ImportMatch valida participantes antes de gravar, atualiza dados atomicamente, mantém unicidade da projeção por conta e não modifica atividade Solo/Duo ao importar Flex.
- Eficiência da API: cache compartilhado de Match-V5 por partida com lock, reutilização entre contas e enriquecimento de registros antigos sem duplicar partidas. Identidade resolvida persistida entre retentativas da fase snapshot. Ranking e observação de liga são confirmados com checkpoint da fase. Atualização recente de até 60 jogos por padrão, encerramento ao reencontrar página inteiramente detalhada e modalidade completa separada; suporte às filas 420 e 440. Removido atraso fixo de 2 segundos; orçamento compartilhado continua ativo e agora também respeita saturação informada por headers e pausa global Retry-After. Não há chamadas de jogadores individuais para montar placar.
- Interface: seis abas (visão geral, partidas, campeões, padrões, recordes e evolução), barra dos últimos 20 campeões com resultados, cards de partidas, placar expansível de participantes, destaque do dono, itens, feitiços, runas principais, objetivos e banimentos. Filtros de período/fila/campeão/adversário/posição/resultado, paginação preservando filtros e ações separadas de histórico completo.
- Estatísticas: AccountStatistics ampliado para CS/ouro/dano por minuto, participação e visão por campeão; AccountInsights isolado para médias com amostras, sequências, máximos, grupos por lado/posição/duração/dia/horário/resultado anterior/rendição/adversário, resultados diários e companheiros no mesmo time. Coocorrência não é identificada como grupo premade. Ausência de métricas permanece ausência; não há média populacional, MMR inventado ou LP retroativo por partida.
- Recursos estáticos: catálogo público oficial Data Dragon 16.20.1, suporte RiotAssets/GameLabels, componentes game-icon/match-card e comando npm.cmd run assets:update, sem dependências adicionais. Ícones resolvidos por catálogo local e carregados do CDN, sem HTTP backend ao renderizar. A versão atual dos ícones é informada; não se afirma representar atributos históricos.
- Arquivos principais: nova migration e modelos; Actions/Sync; RiotService e SyncAccount; Queries/AccountInsights e AccountStatistics; ContaController/SyncController; views de detalhes, analytics, champion-table, game-icon e match-card; resources/data/riot-assets.json; scripts/update-riot-assets.mjs; package.json; testes MatchAnalyticsTest, MigrationSafetyTest e fixture/suíte Browser; README, ARQUITETURA, OPERACAO e este diário.
- Verificação de banco: primeira tentativa no MySQL local revelou incompatibilidade do servidor com timestamp obrigatório sem default depois de um timestamp anulável. Corrigido para datetime nos campos obrigatórios played_at/observed_at da nova migration; a primeira criação havia falhado antes de criar tabela. Nova execução concluiu a migration no MySQL, preservando contas e partidas. Teste de upgrade foi ampliado para as duas migrations novas, com rollback somente no SQLite isolado de testes.
- Ajustes finais: preservação da fila Flex ao redirecionar após agendamento e dos filtros ao atualizar os dados; identificação explícita do ranking Solo/Duo no cabeçalho; leitura de teamPosition prioritária e última atividade Solo/Duo protegida contra importação Flex.
- Verificações executadas: 75 testes PHP passando, 291 assertions; 3 testes JavaScript passando; 4 testes Playwright em desktop e celular passando, incluindo expansão do placar, abas, ausência de overflow global e erros JavaScript; build Vite, Pint e compilação/limpeza das views aprovados; migrate:status confirma as migrations locais aplicadas. Screenshots desktop/mobile gerados em storage/framework/testing/screenshots, com inspeção visual do placar. Corrigidos nomes acessíveis duplicados na tabela e separação do nome do jogador para leitura/teste do placar. O atualizador do catálogo foi executado com sucesso usando somente dados públicos.
- Achados atualizados: A04/A12/A13 ampliados com atualização incremental, cache e checkpoints; A15 ampliado com projeção sem payload, eager loading, agregação e cursor; A19 ampliado com navegação por abas e validação desktop/mobile; A20 atualizado com operação e manutenção do catálogo. Não se declara benchmark de produção.
- Limitações: chamadas autenticadas à Riot não foram executadas nesta entrega. Dados antigos só ganham detalhes quando reencontrados; a API pode não disponibilizá-los. Ranking continua Solo/Duo e snapshots começam agora; não há LP exato por partida ou reconstrução histórica. Sem timeline extra, comparação populacional ou scraping. Cache sem expiração automática requer planejamento de armazenamento em escala. Custo das análises cresce com o recorte, embora não carregue todos os modelos simultaneamente.
- Próximo passo operacional: recarregar o aplicativo; reiniciar composer dev caso o worker anterior ainda esteja em execução; sincronizar conta para obter os detalhes reais e usar a importação completa para partidas antigas. Nenhum commit/push ou deploy foi realizado; trabalho permanece na dev.

### 2026-10-16 — Entrada 006: nova verificação local após reset do usuário

- Objetivo: verificar novamente o e-mail da conta local a pedido do usuário, que informou ter executado migrate:fresh.
- Alterações: confirmado ambiente local e um único usuário existente, ID 1; aplicado markEmailAsVerified em transação e registrado user.email_verified_local_test em audit_events. Nenhuma migration, seed ou reset foi executado pelo agente.
- Verificação: usuário relido com hasVerifiedEmail verdadeiro; senha e cargo user conferidos e preservados. Nenhum dado pessoal ou credencial foi incluído neste registro.
- Achados: A09 permanece corrigido; a exigência de verificação continua ativa no aplicativo.
- Limitações: sem envio de e-mail real; testes e build não repetidos, pois a operação alterou somente dados locais e este diário.
- Próximo passo: atualizar o navegador e acessar o painel.

### 2026-10-16 — Entrada 007: correção de destinos antigos após login e revisão de rotas

- Objetivo: investigar Not Found após autenticação/cadastro e revisar casos semelhantes, conforme relato do usuário.
- Evidência local: ambiente local, rotas sem cache; uma sessão conservava url.intended para /contas/2, enquanto Conta ID 2 não existia após o reset informado. Nenhuma sessão, senha, token ou endereço pessoal foi copiado para o relatório. O uso direto de redirect()->intended não verificava existência nem acesso ao destino.
- Hipótese descartada: inicialmente suspeitei de perda do caminho-base por URLs relativas. Testes reproduzindo /painel/public passaram antes da correção; o UrlGenerator recompõe a base ao redirecionar. Essa suspeita não foi tratada como causa confirmada. O endereço exato do erro foi solicitado ao usuário; a correção foi baseada no destino obsoleto efetivamente encontrado e nos cenários reproduzidos.
- Correções de autenticação: criada Actions/Auth/RedirectAfterAuthentication e aplicada a login, confirmação de senha, prompt/reenvio/verificação de e-mail. Valida mesma origem/porta, caminho-base, rota GET permitida, existência/propriedade de contas e cargo administrativo. Destinos antigos, externos, sem acesso, formulários de autenticação ou endpoints JSON retornam ao dashboard. Destinos válidos mantêm filtros. Usuário não verificado abre verification.notice e conserva destino para depois da confirmação.
- Cadastro: regenera sessão, remove url.intended herdada e vai diretamente à verificação de e-mail. Logout e exclusão do perfil usam rota home nomeada. GET /contas, anteriormente 404, agora abre dashboard preservando days. Não foi criado redirecionamento global de 404 nem alterada a autorização de recursos.
- Arquivos: nova ação de autenticação; seis controllers Auth e ProfileController; routes/web.php; testes RegistrationTest, IntendedRedirectTest e SubdirectoryRedirectTest; suíte Browser/dashboard.spec.js; ARQUITETURA, OPERACAO e este diário.
- Verificações: 99 testes PHP aprovados, 389 assertions. Novos cenários cobrem destinos inexistentes/deletados, conta de outro usuário, administração sem acesso, loop de autenticação, endpoint JSON/mutação, origem/porta externa, filtros válidos, usuário não verificado e cadastro com sessão antiga. Testes de subpasta cobrem login, cadastro, link assinado de e-mail, reenvio, confirmação, logout, exclusão, middleware guest e lista de contas. Oito cenários Playwright aprovados em desktop/mobile, incluindo cadastro, login posterior, conta apagada e /contas. Cache de rotas gerado com sucesso e removido ao final; Pint aprovado após normalização.
- Achados: novo A21 (destino obsoleto após autenticação) corrigido com evidência de testes e sessão local; A09 e A18 preservados e ampliados. A19 revisado nos novos fluxos de navegação.
- Limitações: não houve teste de entrega real de e-mail, alteração de .env, reset, seed, migração de dados, exclusão de sessões ou uso da Riot API. Build não repetido porque não houve alteração em assets, dependências ou views. A existência do destino é validada no momento do login; remoção posterior de um recurso ainda resulta corretamente em 404.
- Próximo passo: atualizar o navegador e entrar novamente. Trabalho continua na dev, sem commit/push.

### 2026-10-16 — Entrada 008: retorno das barras de progresso e cortes diários

- Objetivo: restaurar as barras até o próximo elo, incluindo referências reais de Grão-Mestre e Desafiante consultadas diariamente, conforme pedido explícito.
- Implementação: novo RankProgress calcula avanço pelas quatro divisões de Ferro a Diamante e monta referências de Mestre+ com metas independentes. Componente rank-progress compartilhado no dashboard e nos detalhes, com semântica progressbar, LP atual/meta, LP faltantes ou acima do corte, fonte e horários. Valores visuais limitados a 0–100%; sem ranking confirmado não há progresso inventado. Solo/Duo identificado, inclusive com filtro de partidas Flex.
- Fonte e custo: inspeção das páginas públicas indicadas revelou que __NEXT_DATA__ contém os dois cortes por plataforma. ReplaysCutoffSource usa uma única página pública fixa do Replays.lol para consultar todas as seis plataformas, sem token Riot. Valida contexto da página, duplicatas/região, tipos, limites, ordem GM/Desafiante e timestamp recente. Não usa regex genérica para pegar qualquer LP, fallback 600 nem GM+400.
- Persistência/agenda: criada migration 000003 e model RankCutoff, com corte por região/fila/elo, fonte, horário da fonte/consulta e tentativa/falha. RefreshRankCutoffs usa lock e marca tentativa antes do HTTP; atualiza pares de cortes atomicamente e preserva valores anteriores em falha. Limite normal de uma consulta global por dia civil de Brasília, inclusive após reiniciar processo. Comando riot:cutoffs agendado às 03:10 America/Sao_Paulo; --force permite intervenção explícita no mesmo dia. Nenhum acesso ao painel dispara scraping.
- Banco local e operação: migration 000003 aplicada com sucesso ao MySQL local sem reset. Primeira execução real de riot:cutoffs concluiu a atualização; segunda informou reaproveitamento da consulta do dia. Schedule:list confirmou agendamento. Nenhuma chamada autenticada à Riot foi usada.
- Apresentação de falhas: fonte indisponível ou dados com mais de 36 horas mantêm referência antiga com aviso/barra cinza. Ausência de valor mostra indisponibilidade. Corte alcançado é referência e não garantia de promoção/permanência. Não foi restaurado cálculo de reserva de decay.
- Arquivos: migration/model RankCutoff; ReplaysCutoffSource; Actions/Sync/RefreshRankCutoffs; comando homônimo; Queries/RankProgress; ContaController; componente rank-progress e views dashboard/detalhes; routes/console; testes RankProgressTest/MigrationSafetyTest; fixture/suíte Browser; README, ARQUITETURA, OPERACAO e diário.
- Testes: 113 testes PHP aprovados, 454 assertions. Cobrem divisões, teto visual, metas independentes, uma chamada diária, troca de dia em Brasília antes de 24 horas, concorrência, fonte vazia/alterada/inconsistente, preservação de valores e páginas sem HTTP. Teste de upgrade expandido às três migrations novas, com rollback exclusivamente no SQLite isolado.
- Verificações finais adicionais: 10 cenários Playwright aprovados em desktop/mobile, incluindo barras normais e Mestre+, LP acima do corte, referência independente e ausência de overflow global. Capturas desktop/mobile inspecionadas após as correções. Três testes JavaScript, build Vite, Pint e compilação/limpeza Blade aprovados. A consulta real de dados públicos e o reaproveitamento diário também foram verificados.
- Revisão visual: captura mobile mostrou uma diretiva Blade concatenada ao texto na mensagem de referência alcançada; corrigida para bloco multilinha e adicionada regressão. A primeira execução do novo cenário Browser também encontrou seletor exato que omitia a seta do link; seletor corrigido para o nome acessível existente. São ajustes desta implementação, sem falhas deixadas deliberadamente.
- Achados atualizados: A14 passa a disponibilizar cortes observados independentes, mantendo removidas as previsões sem base e os fallbacks antigos; A15 mantém leitura em lote, sem rede/queries em Blade; A19 ganha barras acessíveis e responsivas; A20 ganha comando/agenda e orientação operacional.
- Limitações: o Replays.lol é fonte externa e pode alterar formato ou ficar indisponível; não é fornecimento oficial de promoção. A periodicidade diária não representa variações intradiárias. O scheduler precisa permanecer ativo; force pode repetir a consulta por decisão do operador. Sem teste de carga, garantia de promoção ou previsão de decay.
- Próximo passo operacional: recarregar o painel; manter scheduler ativo (ou reiniciar composer dev se ele estava aberto antes da alteração da agenda). Trabalho permanece na dev, sem commit/push.

### 2026-10-16 — Entrada 009: uma única barra para o próximo elo

- Objetivo: ajustar a visibilidade das barras conforme a correção do usuário.
- Alterações: RankProgress agora exibe somente a meta imediatamente seguinte. Mestre vê Grão-Mestre; Grão-Mestre vê Desafiante; Desafiante não apresenta barra e informa que já está no elo mais alto. Ferro–Diamante mantêm somente o próximo elo. A atualização diária continua obtendo ambos os cortes compartilhados.
- Arquivos: Queries/RankProgress, RankProgressTest, Browser/dashboard.spec.js, ARQUITETURA, OPERACAO e diário.
- Verificações: 14 testes direcionados PHP passaram (67 assertions); dois cenários Playwright passaram, desktop e celular, verificando uma única barra e ausência da meta Desafiante em Mestre. Pint executado. Sem alteração de assets/dependências, build e suíte completa não repetidos.
- Achados: A14/A19 mantidos, com apresentação restrita ao próximo elo.
- Limitações: sem alteração de banco, consultas externas ou agenda. As referências continuam sujeitas às condições registradas na Entrada 008.
- Próximo passo: atualizar o painel para visualizar a barra correspondente ao elo atual.

### 2026-10-16 — Entrada 010: buscas limitadas à temporada 2026

- Objetivo: atender ao relato de busca excessivamente longa e restringir as novas importações ao início das ranqueadas de 2026.
- Investigação: listagem Match-V5 não recebia filtro de datas; full percorria todo o histórico disponível até o teto operacional. No banco local consultado, não havia execuções ativas naquele momento. Não se atribui ao relato um loop infinito reproduzido sem evidência.
- Fonte do limite: notas oficiais 26.1 indicam início em 08/01/2026 às 12h do servidor; documentação da Riot lista fusos. Para BR, 15h UTC. Implementado HistoryWindow para as seis plataformas suportadas, usando o início do ano competitivo 2026 solicitado, não apenas a subdivisão temática atual.
- Alterações: StartSync salva history_window no contexto antes de agendar; fim fixado no início da busca, preservado entre snapshot/retries/checkpoints. RiotService envia startTime/endTime em segundos na listagem de IDs. Snapshot remove somente dados de identidade temporários e preserva a janela. ImportMatch valida o início da partida também para cache, sem gravar dados anteriores ao início ou posteriores ao limite final.
- Encerramento: página repetida (hash normalizado, inclusive fora de sequência) interrompe full em estado parcial; detalhe fora da janela também interrompe, evitando continuar em jogos antigos se a API ignorar filtros. Checkpoints antigos sem janela que já avançaram são cancelados sem HTTP e pedem nova sincronização; não se reaplica offset de período antigo a outro. Recent continua com teto de 60; full agora é exclusivamente temporada 2026.
- Interface: opção renomeada para “Importar temporada 2026”, explicação do período e exibição das datas fixadas no status. Mensagens de conclusão identificam a temporada. Registros já armazenados permanecem intactos.
- Arquivos: Support/HistoryWindow; RiotService; Actions/Sync/StartSync, AdvanceSync e ImportMatch; view de conta/sync-status; testes SeasonHistoryTest e fixtures de SynchronizationTest/MatchAnalyticsTest; README, ARQUITETURA, OPERACAO e diário. Nenhuma migration necessária: contexto já é JSON persistente. Validação de região em StartSync agora produz erro de formulário em vez de ValueError para cadastro legado inválido.
- Verificações: 125 testes PHP passaram, 482 assertions. Incluem seis fusos, parâmetros da API, fim congelado, rejeição de jogo iniciado antes do corte mesmo terminando depois, encerramento de checkpoint legado, repetição de página, resposta fora da janela e preservação de registros anteriores. Dois cenários de agendamento/cancelamento no navegador passaram em desktop/mobile. Pint, build Vite e compilação/limpeza Blade aprovados. Sem consumo da Riot API real.
- Achados: A04/A12/A13 ampliados com escopo temporal fixado e proteções de avanço; A15 mantém importação em fila e checkpoint, sem rede em views.
- Limitações: a temporada ainda pode conter muitas partidas; a duração depende do volume e dos limites da API. O início 2026 é explícito e deve ser atualizado quando o escopo do produto mudar. Não houve exclusão de histórico ou reset. Workers persistentes precisam reiniciar para carregar código novo.
- Próximo passo: reiniciar composer dev caso estivesse aberto e iniciar uma nova sincronização da conta.

### 2026-10-16 — Entrada 011: remoção autorizada de partidas anteriores à temporada e promoção local

- Objetivo: consultar o banco, remover partidas anteriores ao início da temporada 2026 e conceder administração ao usuário, por solicitação explícita.
- Inspeção: confirmado ambiente local, único usuário ID 1, uma conta BR e ausência de sincronizações ativas. Inventário: 625 participações, sendo 105 anteriores ao corte e 520 no período.
- Operação: transação com lock do usuário e rechecagem de execuções ativas; removidas exatamente as 105 participações anteriores a 08/01/2026 às 12h de Brasília. Classificação temporal pelo início do payload, started_at do cache ou fim menos duração; para legado sem duração, fim anterior ao corte. Verificada possibilidade de limpar jogos associados sem referências restantes: nenhum cache vinculado tornou-se órfão nesta operação (zero jogos e zero detalhes adicionais removidos).
- Administração: utilizado o comando existente app:promote-admin internamente, sem expor e-mail na linha de comando ou no relatório. Usuário ID 1 promovido de user para admin; senha e estado de verificação de e-mail preservados e conferidos.
- Integridade: recalculada a última atividade Solo/Duo das contas; registrados matches.preseason_removed_local e user.promoted_by_console em audit_events. Nenhum reset, migration, seed ou chamada externa.
- Verificação: releitura independente confirmou 520 participações restantes, zero anteriores ao corte e cargo admin. Sem mudança funcional de código; testes/build não repetidos.
- Arquivos/dados: partidas, contas (última atividade), users (cargo), audit_events e este diário.
- Achados: A04/A16 preservados; limpeza histórica realizada com autorização específica, sem criar rotina automática de exclusão.
- Próximo passo: atualizar a página para conferir as estatísticas e o menu administrativo.

### 2026-10-16 — Entrada 012: diagnóstico de partidas sem detalhes

- Objetivo: explicar por que o histórico real do usuário não apresenta placares detalhados.
- Consulta local: 520 participações existentes, zero details_synced_at preenchidos, zero vínculos riot_match_id, zero riot_matches e zero match_participants. As partidas possuem apenas resumos, sem duração/dados dos times. A última sincronização registrada terminou com cursor/processed/imported 625, mode recent, mensagem da implementação anterior.
- Processo: localizado worker queue:work iniciado às 20:47 de 16/10; os arquivos de importação/sincronização foram atualizados posteriormente (últimas alterações às 22:19/22:20). O processo persistente mantém código carregado, consistente com a execução antiga (625 em modo que atualmente limita 60).
- Conclusão: a tela não tem dados detalhados para renderizar; alterações no schema/interface não completam resumos antigos automaticamente. É necessário reiniciar o worker e realizar importação da temporada 2026 para enriquecer todos os jogos disponíveis. Sincronização comum cobre até 60 recentes.
- Verificações/limites: inspeção de banco, código e datas do processo; nenhuma chamada à Riot, agendamento novo, exclusão ou alteração funcional executada nesta consulta. Testes/build não repetidos por ser diagnóstico.
- Arquivos: somente este diário. Próximo passo operacional: reiniciar composer dev/worker e importar temporada 2026.

### 2026-10-16 — Entrada 013: limpeza autorizada de contas e partidas

- Objetivo: deixar contas de LoL e partidas vazias para novo cadastro/importação, conforme pedido explícito de fresh.
- Inspeção: ambiente local, único usuário ID 1 com cargo admin, uma conta, 573 participações e duas execuções de sincronização; nenhuma ativa. Cache de jogos/participantes e snapshots de ranking já estavam vazios.
- Operação: obtidos locks das execuções e lock transacional do usuário; removida a conta com cascata de suas 573 partidas e duas execuções. Verificada fila database para tarefas SyncAccount (nenhuma pendente), preservando outros tipos de jobs. Limpeza de cache compartilhado sem referências verificada, sem registros adicionais existentes.
- Preservação: usuário relido e todos os atributos comparados com o estado anterior; cargo admin, senha e verificação de e-mail preservados. Cortes, configurações, sessões e histórico de auditoria preservados. Sem migrate:fresh, alteração de schema ou reset de sequências, evitando reutilização de IDs de jobs antigos.
- Verificação: todas as tabelas de contas, partidas, execuções, snapshots, jogos e participantes confirmadas vazias dentro da transação. Evento accounts.local_reset_requested registrado com contagens, sem dados pessoais. Sem chamada externa.
- Arquivos/dados: contas e dependências, audit_events e este diário. Testes/build não repetidos por ser operação de dados sem alteração funcional.
- Próximo passo: reiniciar composer dev/worker antes de cadastrar e sincronizar novamente, para utilizar o importador atualizado de detalhes e o limite de temporada.

### 2026-10-16 — Entrada 014: reformulação visual e de organização dos dados

- Objetivo: melhorar a disposição das páginas, adicionar gráficos e separar confrontos por posição com retratos, mantendo amarelo e roxo e uma identidade mais próxima de LoL.
- Referências: leitura direta dos três sites indicados foi bloqueada pela ferramenta web; navegação pelo Edge permitiu observar LeagueOfGraphs, OP.GG e DEEPLOL. Usadas como referência a hierarquia de perfil/ranking, resumo estatístico, gráficos e disposição compacta de partidas e times. Não houve cópia de páginas, importação de dados desses perfis, login externo ou reprodução de scores/comparações populacionais.
- Design: tokens Tailwind 4 roxos e dourados, fundos/superfícies, botões, bordas, contraste secundário, foco e tipografia local; shell/sidebar, dashboard, autenticação e home alinhados. Header da conta com arte oficial do campeão mais usado (resolvida por catálogo), ranking/LP/barra e ações principais. Sem imagens geradas ou bibliotecas adicionais.
- Organização: navegação por sete abas e fixa ao rolar; filtros básicos/avançados, recolhidos por padrão no celular com resumo do recorte. Status final de sincronização compacto; importação ampliada e gerenciamento/exclusão em seções recolhidas. Mantidos cancelamento, validação, filtros, ações e proteção de acesso.
- Dados e gráficos: anel de vitórias, barras empilhadas de resultados diários, distribuição por posição e linha das observações de ranking. Métricas agrupadas em Combate, Economia e Visão; campeões em destaque e últimos jogos priorizados. Estados vazios explícitos. Tabela diária completa e registro de snapshots disponíveis como alternativas; nenhum MMR/LP retroativo inventado.
- Confrontos: nova aba com agrupamento por Topo/Selva/Meio/Atirador/Suporte, retratos, número de jogos, vitórias/derrotas, taxa e KDA. Links mantêm escopo/filtros e abrem histórico do adversário e posição. O mesmo campeão é agregado separadamente por posição. Lista por posição mostra oito adversários e permite expandir o restante. Taxa é resultado da partida, sem alegar domínio de rota.
- Histórico: jogos agrupados por dia, prévia dos times emparelhada por posição, destaque do jogador, controles de visão completa/compacta e placar preservado. ImportMatch e a prévia deixam posições ambíguas sem adversário arbitrário.
- Estrutura: AccountMatchups faz agregação SQL com o Builder já autorizado e somente na aba correspondente; PersonalCharts prepara pontos; partials separadas e novos componentes de gráficos, posições, confronto e prévia dos times. RiotAssets ganhou splash por catálogo. Não há consultas em Blade, nova dependência, migração ou chamada autenticada adicional à Riot.
- Arquivos: app.css; layouts main/guest, home/dashboard/contas/show e componentes stats-summary/champion-table/match-card; partials analytics/sync-status/overview/recent-form/matchups/patterns/progress; componentes role-icon, winrate-ring, daily-chart, trend-chart, match-lineup e opponent-row; Queries/AccountMatchups, Support/PersonalCharts/RiotAssets, ContaController e ImportMatch; testes AnalyticsLayoutTest/MatchAnalyticsTest e fixture/suíte Browser; README, ARQUITETURA, OPERACAO e diário.
- Verificações: 130 testes PHP passaram, 511 assertions; três testes JavaScript passaram; 12 cenários Playwright aprovados em desktop/mobile, incluindo navegação nas sete abas, gráficos, filtros de posição, visão compacta sem ocultar o placar, formulários e ausência de overflow global. Após ajustes finais, os dois cenários de gráficos/confrontos foram repetidos com sucesso. Pint e compilação/limpeza Blade aprovados; build Vite passou, CSS 66,86 kB (12,12 kB gzip), JS permanece 56,28 kB. Capturas de overview, confrontos, gráficos e histórico inspecionadas.
- Ajustes durante validação: corrigida expectativa de teste para imagem Aatrox.png do catálogo (ID não é nome de arquivo de campeão); corrigido separador literal introduzido no fechamento Blade do status. Suítes posteriores passaram. As capturas finais usam exclusivamente dados fictícios do banco isolado de navegador.
- Achados: A15 preservado/ampliado com agregação por posição e preparação de gráficos; A19 ampliado com hierarquia, contraste, filtros mobile, densidade, navegação e testes; A20 com documentação de uso atualizada.
- Limitações: gráficos e confronto dependem dos campos realmente importados. Dados anteriores ambíguos já persistidos não foram reescritos; novas importações seguem a regra de unicidade da posição. Não houve benchmark de carga nem certificação formal de acessibilidade. Banco do usuário não foi limpo, preenchido ou alterado nesta sessão; fixtures de demonstração ficaram isoladas em storage/framework/testing/browser.sqlite.
- Próximo passo: atualizar o navegador; importar partidas se o painel estiver vazio após a limpeza anterior. Trabalho permanece na dev, sem commit/push.

### 2026-10-16 — Entrada 015: temporadas administráveis e histórico compacto por padrão

- Objetivo: abrir o histórico compacto, cadastrar 2026 como temporada atual, permitir ao administrador cadastrar e iniciar próximas temporadas e filtrar os dados por temporada.
- Ciclo: catálogo Season com estados não iniciada/atual/encerrada; somente 2026 é criada pela migration. Administração em /admin/temporadas, protegida por autenticação, verificação de e-mail e cargo admin (mod não pode alterar). Cadastro não inicia a contagem. Iniciar uma temporada encerra a anterior e inicia a escolhida no mesmo segundo UTC, em transação com locks ordenados e unicidade da atual no banco. A troca é auditada com ator e IDs; temporadas iniciadas não são reabertas.
- Preservação e datas: migration aditiva 000004 cria seasons e partidas.started_at. Mantidos os seis inícios regionais de 2026. Preenchimento em lotes pelo início do cache ou fim menos duração, sem excluir contas, partidas ou checkpoints. Novas importações gravam o início exato pelo protocolo existente. Consultas usam intervalo inclusivo no início/exclusivo no fim; uma partida iniciada antes da troca pertence à anterior mesmo terminando depois. Para resumo legado sem início/duração/cache, played_at é aproximação documentada.
- Filtros: dashboard e conta selecionam a atual por padrão; temporadas encerradas continuam disponíveis. SelectedSeason valida IDs e bloqueia rascunhos. O intervalo é aplicado antes das estatísticas, campeões, posições, confrontos, recordes, parceiros e histórico; opções de campeão/adversário respeitam temporada/fila/período. Gráfico de ranking filtra observações pelo intervalo e dias. Ranking e barra de próximo elo continuam identificados como atuais. Abas, links, paginação e formulários de importação preservam a seleção. Período “Toda a temporada” permite consultar o intervalo completo.
- Sincronização: StartSync recebe a temporada selecionada e congela a janela com season_id/nome/início/fim antes do dispatch; importa tanto atual como encerradas dentro de seus limites. AdvanceSync respeita o encerramento em checkpoints; ImportMatch relê com lock na transação para proteger uma troca concorrente contra janelas desatualizadas. Janelas anteriores por nome 2026 seguem compatíveis. Mantidos limite recente, paginação, cache compartilhado e ausência de HTTP nas views.
- Interface: histórico inicia compacto, inclusive classe inicial antes do Alpine; “Visão completa” abre a prévia dos times e o toggle continua funcional. Placar completo permanece acessível no modo compacto. Novo item Temporadas somente para admin; cadastro, estado, início/fim e troca com confirmação nativa na página administrativa. Destino após login reconhece a nova página somente para admin.
- Estrutura/arquivos: Model Season; Actions/Seasons/ActivateSeason; Queries/SelectedSeason; SeasonController e ContaController/SyncController; HistoryWindow, StartSync, AdvanceSync, ImportMatch, AccountStatistics, Partida; migration 000004; rotas, layout principal, dashboard, conta/analytics, componentes season-select/period-filter; RedirectAfterAuthentication; testes SeasonsTest, SeasonHistoryTest, RiotClientTest, MigrationSafetyTest e Browser; README, ARQUITETURA, OPERACAO e diário.
- Verificação de backend: 140 testes PHP passaram, 582 assertions. Cobertura nova inclui cadastro/troca/auditoria, exclusividade da atual, bloqueio de reativação, permissões, seleção inválida/rascunho, limites exatos, isolamento por usuário, importação encerrada, corte de janela desatualizada, snapshots e backfill aditivo. Três testes JavaScript passaram; Pint, build Vite, listagem de rotas e compilação/limpeza Blade aprovados. Nenhuma chamada real à Riot para testes.
- Ajustes de validação: rollback SQLite exige retirar o índice antes da coluna; teste de cliente agora usa RefreshDatabase por depender do catálogo. Corrigida codificação de identificador acentuado do teste e expectativa da janela, cujo nome agora é string. Falhas intermitentes de navegação no Playwright exibiam requisições canceladas e carregamento de /@vite/client pelo servidor de desenvolvimento existente. AppServiceProvider isola ambiente testing do arquivo hot do desenvolvedor e usa o build; não altera o servidor do usuário.
- Operação local: migration 000004 aplicada com sucesso, sem fresh/reset. Releitura confirmou exatamente uma temporada, 2026 ativa, e 252 partidas com started_at preenchido. Futuras temporadas de demonstração ficam exclusivamente no SQLite isolado do navegador, sem cadastrar 2027 no banco de negócio.
- Limitações: não há reconstrução de elo histórico, edição retroativa de cortes ou promessa de disponibilidade de partidas antigas na Riot. Testes de troca são transacionais/funcionais; não foi executado teste de carga concorrente. Worker persistente precisa ser reiniciado pelo fluxo normal para carregar o novo código.
- Próximo passo: abrir Temporadas no menu administrativo; para consultar toda uma temporada encerrada, selecionar também “Toda a temporada” no período.

- Resultado final de navegador da Entrada 015: após isolar o Vite, os 14 cenários Playwright passaram em desktop/mobile. Conferidos padrão compacto, alternância completa/compacta com placar acessível, navegação, filtros/gráficos e cadastro administrativo de temporada sem ativação automática. Capturas de Temporadas nos dois tamanhos inspecionadas; nenhum overflow global detectado. As tentativas anteriores com HMR permanecem registradas acima, sem ocultar as falhas observadas.

### 2026-10-16 — Entrada 016: verificação local da segunda conta de usuário

- Objetivo: confirmar o e-mail da outra conta comum a pedido do usuário, para testes locais.
- Operação: identificadas duas contas no banco local; somente a de ID 2, cargo user, estava sem verificação. Aplicado markEmailAsVerified em transação com lock e registrado user.email_verified_local em audit_events.
- Verificação: releitura confirmou e-mail verificado, cargo user e senha preservados. Nenhuma alteração nas contas de LoL, partidas ou temporada.
- Arquivos/dados: users, audit_events e este diário. Sem alteração funcional de código; testes/build não repetidos por ser operação pontual de dados.
- Próximo passo: acessar o painel com a conta comum.
