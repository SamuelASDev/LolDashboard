# Operação e atualização

## Instalação existente

Faça backup do banco/configuração, pare workers antigos e use manutenção durante atualização. Instale lockfiles, gere assets, aplique migrations e reinicie processos. Não sobrescreva `.env` nem gere outra `APP_KEY`.

~~~powershell
composer install --no-dev --no-interaction --prefer-dist --optimize-autoloader
npm.cmd ci
npm.cmd run build
php artisan migrate --force
php artisan config:cache
php artisan route:cache
php artisan view:cache
php artisan queue:restart
~~~

A migration `2026_10_16_000001_add_sync_identity_and_auditing` adiciona campos, índices e tabelas sem reset. Não confirma automaticamente o histórico antigo. O aviso de legado permite reimportar e confirmar o que a API ainda disponibiliza.

Verificação de e-mail agora é efetiva: usuários antigos não confirmados seguem para verificação. Configure entrega real antes da publicação; não marque todos como verificados para contornar o fluxo. `MAIL_MAILER=log` é para desenvolvimento.

## Configuração

| Variável | Padrão | Uso |
| --- | --- | --- |
| `RIOT_KEY` | vazio | Chave apenas no servidor |
| `RIOT_QUEUE_CONNECTION` | `database` | Conexão assíncrona configurada |
| `RIOT_REQUESTS_PER_SECOND` | `15` | Orçamento local de curto prazo |
| `RIOT_REQUESTS_PER_TWO_MINUTES` | `80` | Orçamento compartilhado de 120 segundos |
| `DB_QUEUE_RETRY_AFTER` | `180` | Maior que timeout do job/lease |
| `APP_LOCALE` | `pt_BR` | Locale |
| `MAIL_*` | desenvolvimento | Verificação/reset |
| `CACHE_STORE` | `database` | Store compartilhado para locks/orçamento |
| `SESSION_SECURE_COOKIE` | definir em HTTPS | Cookies somente HTTPS em produção |

`config/riot.php` define página de 20 IDs, máximo de 500 páginas, 5 falhas transitórias por etapa, prazo de 24 horas, timeout HTTP de 15 segundos/conexão de 5. Snapshot faz até duas chamadas; configure orçamento por segundo que comporte ambas. Ajuste à chave; orçamento local não garante limite remoto.

Todos os servidores devem compartilhar cache e namespace. Cache `array` é exclusivo de testes. Redis/SQS/Beanstalkd precisam de conexão configurada no Laravel; drivers síncronos/nulos são recusados pelo fluxo de sincronização.

## Processos

~~~powershell
php artisan queue:work database --queue=riot --sleep=1 --tries=3 --timeout=90
php artisan schedule:work
~~~

Troque a conexão se necessário. Em produção, supervisione workers e configure cron para `php artisan schedule:run` a cada minuto. Não dependa de terminal aberto. Job tem timeout 90 s, trava 120 s e `retry_after` padrão de 180 s. Configure equivalentes em outros backends. No Windows, timeout de worker depende de sinais; HTTP possui timeout independente.

`composer dev` inicia web/Vite/worker/scheduler para desenvolvimento; não é servidor de produção. Reinicie workers no deploy.

### Worker temporário por arquivo PHP em hospedagem compartilhada

O arquivo `scripts/cron-worker.php` permite executar a fila sem comando shell extenso e sem o executável flock. Aceita somente PHP CLI, encontra a raiz por __DIR__, usa lock exclusivo não bloqueante pelo flock nativo do PHP e executa queue:work com a conexão/fila de config/riot.php. O lock é liberado no término; uma invocação sobreposta sai sem iniciar outro worker. Protege disparos nesse servidor, sem substituir locks/cache compartilhados em múltiplos servidores.

Configure uma tarefa a cada minuto chamando o PHP CLI com o caminho absoluto de `scripts/cron-worker.php`. No tipo Custom, informe `CAMINHO_PHP CAMINHO_PROJETO/scripts/cron-worker.php`. No tipo PHP, informe o caminho do script no campo destinado ao arquivo, sem repetir um prefixo PHP já adicionado pelo painel. Não acrescente esse caminho nem flock depois de artisan: são entradas distintas.

Mantenha outra tarefa de `artisan schedule:run` a cada minuto; o script do worker não executa a agenda. Substitua a antiga tarefa do worker, evitando dois modelos de execução. storage/framework deve permitir criar o lock pelo usuário da cron. Não remova o arquivo de lock com processo ativo; ele é local e ignorado pelo Git.

Verificação sem consumir jobs: `php scripts/cron-worker.php --check`. Execução efetiva: `php scripts/cron-worker.php`. O worker usa stop-when-empty, max-time=50, sleep=1, tries=3 e timeout=90; max-time é verificado entre jobs e não impõe término rígido aos 50 segundos. Sinais/timeouts e limites do provedor precisam de homologação. O arquivo fica fora de public/ e não deve ser chamado por HTTP. Falhas retornam status não zero e diagnóstico sem mensagem/trace de exceção.

## Recuperar e acompanhar

- Detalhes da conta e `GET /contas/{conta}/sync` mostram progresso apenas ao dono.
- `php artisan riot:recover` reagenda etapas sem atividade há 5 minutos quando `available_at` permite.
- Scheduler executa recuperação; versão e lock tornam jobs repetidos inofensivos.
- Cancelamento pode pedir nova tentativa se a etapa estiver com a trava. Não mata uma chamada HTTP no meio.
- Prazo de 24 horas encerra a execução. Nova sincronização consulta recentes e pula partidas confirmadas.
- Consulte `queue:failed` e logs sanitizados para falhas de infraestrutura.
- `completed` significa fim da lista disponibilizada pela Riot, não histórico integral desde a criação da conta.

## Administração

~~~powershell
php artisan app:promote-admin email@example.com
~~~

Somente usuário existente pode ser promovido pelo console seguro. O comando não cria senha nem verifica e-mail. Promoções/mutações são auditadas. Painel altera user/mod e protege todos os admins. Moderador tem consulta da lista; não acessa contas Riot de outros usuários.

Exclusão é definitiva, não banimento. Usuário excluído pode se registrar de novo. Sincronização ativa impede exclusão; último admin não pode excluir o próprio perfil.

## Publicação e dados

Sirva somente `public/`, com HTTPS, `APP_ENV=production` e `APP_DEBUG=false`. Restrinja console, storage e backups. Use credenciais de banco apropriadas e mantenha chave Riot fora do cliente. Faça backup antes de migrations e teste restauração em ambiente separado.

Não execute `migrate:fresh`, seed ou rollback para “arrumar contagem”. O rollback antigo de unicidade recusa matches compartilhados. Legado não confirmado pode ter valor histórico.

## Verificação

~~~powershell
composer validate --strict
php artisan test
php vendor/bin/pint --test
npm.cmd test
npm.cmd run build
php artisan route:cache
php artisan route:clear
composer audit
npm.cmd audit
npm.cmd run test:browser
~~~

Browser exige Chromium ou `PLAYWRIGHT_BROWSER_EXECUTABLE`. Sua fixture isola banco/sessão/chave e não deve ser usada em produção.

E-mail real, Riot real, restauração e carga precisam ser validados na infraestrutura de operação. Testes simulados não comprovam esses serviços.

## Histórico de partidas, atualização e catálogo

A página de detalhes possui abas Visão geral, Partidas, Campeões, Confrontos, Padrões, Recordes e Evolução. Em Partidas, expanda “Ver jogadores e placar” para abrir os dois times. Os filtros são preservados ao alternar abas e paginar.

“Sincronizar conta” atualiza a fila selecionada (Solo/Duo por padrão), verificando até 60 jogos recentes com a configuração padrão. Selecione a temporada nos filtros; em “Importação da temporada”, escolha Solo/Duo ou Flex e use o botão de importar. Essa busca usa mais chamadas; pode ser cancelada preservando o que já foi confirmado. Não há promessa de que a API disponibilize toda a temporada. Uma atualização recente concluída não significa cobertura histórica completa.

Resumos já existentes recebem detalhes quando reencontrados pela sincronização. Para enriquecer jogos além da janela recente, solicite a importação completa. Os novos indicadores ficarão indisponíveis até isso acontecer; não execute seeds/reset no banco para preenchê-los.

A migration 2026_10_16_000002 adiciona cache compartilhado, participantes, indicadores, opções de sincronização e observações de liga. Foi aplicada no MySQL local, sem reset. Para outro ambiente, use php artisan migrate --force e reinicie os workers no processo normal de publicação. No desenvolvimento, se composer dev já estava aberto antes da alteração de código, encerre e inicie novamente para carregar o novo worker. Não execute queue:restart sem supervisor ou reinício correspondente.

Os dados completos de cada jogo ficam armazenados para reaproveitamento entre contas. Não há expiração automática nesta versão; acompanhe o crescimento das tabelas riot_matches e match_participants e estabeleça retenção/backup antes de grande escala. A exclusão de um cadastro remove suas projeções; o cache compartilhado da partida é independente e não possui rota de leitura direta.

Para atualizar ícones, nomes de itens, feitiços e runas depois de patches, execute npm.cmd run assets:update e revise/versione resources/data/riot-assets.json. O comando usa exclusivamente o CDN público oficial e não consome a chave Riot. O catálogo entregue corresponde a 16.20.1. Não são exibidos atributos históricos de itens a partir do catálogo atual.

As validações de integração usam respostas simuladas; não foi executada sincronização autenticada real nesta entrega. A suíte de navegador prepara exclusivamente storage/framework/testing/browser.sqlite, com dados fictícios de dez jogadores.

## Redirecionamento para Not Found após login

Após apagar/recriar contas ou resetar o banco, uma sessão pode conservar url.intended apontando para um ID antigo. O aplicativo agora valida o destino antes de redirecionar e retorna ao dashboard se a página/conta não existir ou não pertencer ao usuário. Não é necessário limpar todas as sessões ou resetar o banco para corrigir esse caso.

Novos cadastros e logins de usuários sem e-mail verificado chegam diretamente à tela de confirmação. A verificação não foi desativada. /contas abre a visão geral. Os fluxos também foram testados com caminho-base /painel/public, além de acesso na raiz.

Ao alterar domínio/porta na operação, mantenha APP_URL coerente para links produzidos por processos console/filas. Esta revisão não alterou .env. URLs de retorno com origem/porta antiga são descartadas em favor do dashboard na origem atual.

## Cortes e barras de progresso

A migration 2026_10_16_000003 cria rank_cutoffs e já foi aplicada no MySQL local sem reset. A primeira execução de php artisan riot:cutoffs consultou a fonte pública com sucesso; uma segunda execução confirmou que a consulta do dia é reaproveitada.

O scheduler executa riot:cutoffs diariamente às 03:10 de Brasília, consultando ambos os cortes das seis plataformas em uma só requisição pública ao Replays.lol. Mantenha schedule:work (incluído em composer dev) ou schedule:run via cron ativo. Se o processo de desenvolvimento já estava aberto, reinicie-o para carregar a agenda atualizada. Abrir o painel ou sincronizar contas não dispara o scraper.

Operação manual: php artisan riot:cutoffs. Para repetir uma tentativa que falhou no mesmo dia ou conferir uma correção do parser: php artisan riot:cutoffs --force. O limite diário normal considera o dia civil de Brasília, não uma espera móvel de 24 horas. Falhas não causam repetição em cada acesso e não apagam o último valor; --force permite intervenção imediata.

Pela interface, acesse **Administração → Usuários → Atualizar cortes agora** com um administrador verificado. O botão consulta ambos os cortes de todas as regiões imediatamente, inclusive se a rotina já rodou no dia, e informa o resultado ao retornar à página. Não precisa aguardar cron ou worker. Durante o envio, o botão mostra “Atualizando cortes…”. Há limite de uma tentativa por minuto por administrador e proteção contra consultas simultâneas; falhas preservam os últimos valores. A rotina diária das 03:10 continua ativa. Atualização manual e agenda normal compartilham o registro da tentativa do dia; uma consulta manual antes das 03:10 pode fazer a agenda reaproveitar essa tentativa.

A fonte pode mudar sua estrutura. Em falha ou depois de 36 horas sem referência recente, o painel exibe aviso e barra em cinza. Sem valor anterior, informa indisponibilidade. Verifique fonte, rede e parser antes de forçar nova consulta; não insira números fixos para ocultar falha. Não há chamada autenticada à Riot para buscar os cortes.

Ferro–Diamante mostram progresso pelas quatro divisões até o próximo elo. Mestre+ usam os valores publicados, com horário da fonte e da consulta. São referências Solo/Duo, não garantia de promoção, classificação oficial em tempo real ou cálculo de decay.

As barras exibem somente o próximo elo: Mestre → Grão-Mestre; Grão-Mestre → Desafiante. Desafiante não tem barra de próximo elo. Os dois cortes continuam sendo atualizados, mas cada conta utiliza somente sua meta correspondente.

## Cadastro, troca e filtro de temporadas

O menu **Temporadas** aparece somente para administradores e abre /admin/temporadas. Inicialmente há apenas **2026**, atual, com o início regional já configurado. Cadastre o nome da próxima temporada; ela fica “Não iniciada” e não aparece nos filtros de histórico.

Para começar a contagem, clique em **Iniciar [nome]** e confirme a troca. A temporada atual termina naquele instante e a escolhida passa a ser atual, com o mesmo horário de corte. Partidas iniciadas antes do corte pertencem à anterior, mesmo terminando depois. Não se apagam partidas nem se reabre uma temporada encerrada.

Dashboard e conta oferecem o filtro Temporada. Sem seleção, usam a atual. O filtro Período continua limitando os últimos dias; escolha **Toda a temporada** para consultar o intervalo completo, especialmente temporadas encerradas. Fila, campeão, posição, adversário e resultado combinam com a seleção. O ranking e a barra de próximo elo mostram a classificação atual consultada na Riot, não uma classificação histórica reconstruída.

A sincronização usa a temporada selecionada: atualização comum verifica até 60 partidas recentes do intervalo; importação da temporada amplia a busca nesse mesmo intervalo. Temporadas encerradas continuam disponíveis para consulta e importação, sujeitas à disponibilidade da Riot. Janelas de execuções em andamento respeitam o encerramento e preservam o checkpoint. Nenhuma troca dispara chamadas externas ou importações em massa automaticamente.

A migration 2026_10_16_000004 cria o catálogo e adiciona o início das partidas sem reset. Foi aplicada no banco local. Em outros ambientes, execute php artisan migrate --force e reinicie o worker pelo processo normal. No desenvolvimento, encerre e inicie novamente composer dev se ele estava aberto antes da alteração; workers persistentes precisam carregar o código novo.

## Navegação e gráficos do painel

A visão geral da conta reúne taxa de vitória, ritmo diário, últimos campeões, distribuição por posição e métricas em Combate, Economia e Visão. As abas permanecem acessíveis ao rolar. No celular, os filtros começam recolhidos; abra “Filtros do recorte” para alterá-los. O resumo informa fila, período e posição selecionados.

Confrontos separa adversários por Topo, Selva, Meio, Atirador e Suporte, com retratos, partidas, resultados e KDA. Selecione uma posição ou um adversário para abrir o histórico correspondente, preservando os demais filtros. Os primeiros oito adversários por posição ficam visíveis; a lista restante pode ser expandida. A taxa indica resultado da partida, não vitória de rota. Posições ambíguas não recebem um adversário arbitrário nas novas importações.

O histórico alinha os dois times por posição antes do placar completo. A visão compacta é o padrão e oculta essa prévia para facilitar a varredura, preservando o acesso ao placar. Use “Visão completa” para exibir a prévia dos times. Sincronizações encerradas aparecem resumidas; a importação ampliada e a exclusão do cadastro ficam em seções recolhidas ao final.

Ritmo de jogo mostra os últimos 21 dias com partidas no recorte, sem preencher lacunas com jogos fictícios. Barras permitem leitura por mouse/teclado; a aba Evolução inclui tabela diária completa. A linha de ranking usa observações reais por classificação/divisão/LP; não mede MMR e não reconstrói LP de jogos anteriores. Gráficos, confrontos e retratos dependem dos dados importados; o estado vazio é esperado após a limpeza das contas.

Os testes em ambiente testing usam o build de assets e um caminho hot isolado em storage/framework/testing/vite.hot. Isso impede que um Vite aberto pelo desenvolvedor recarregue as páginas e cancele navegações durante o Playwright. Execute npm.cmd run build antes dos testes de navegador.

## Reserva estimada de decay Mestre+

Aplique a migration aditiva 2026_10_17_000001 pelo procedimento de atualização, sem reset. Ela adiciona somente decay_reference anulável em contas. Gere novamente os assets e reinicie o worker pelo processo normal, pois o snapshot agora invalida a referência quando a Riot confirma ranking abaixo de Mestre.

O indicador aparece no dashboard e nos detalhes de contas Mestre/Grão-Mestre/Desafiante com identidade e ranking confirmados. Ele acompanha o Solo/Duo atual, mesmo ao consultar Flex ou uma temporada encerrada. Abra **Ajustar reserva de decay pelo cliente** nos detalhes para informar quantos dias restam agora. Salvar registra o horário do servidor; jogos anteriores já fazem parte do saldo informado. Use **Voltar à estimativa automática** para remover o ajuste.

Sem ajuste, o intervalo inicial é desconhecido (0–14 dias) e o histórico recente detalhado alimenta a previsão. Não tome um indicador de proteção estimada como confirmação da Riot. Sincronize Solo/Duo e amplie a importação quando o histórico recente estiver incompleto. Ao informar saldo, confira no cliente se ele corresponde ao momento atual; informe novamente após mudança de elo ou divergência. A referência não é transportada para outra temporada.

A previsão soma 1 dia por jogo elegível, limita a 14 e desconta tempo em períodos aproximados de 24 horas. O horário diário do servidor Riot pode diferir. Sem reserva, mostra **Possível decay em andamento** e a regra de 75 LP/dia; nenhuma perda real é confirmada ou aplicada no banco local. Uma queda de LP isolada não serve como prova. Este recurso não exige novo cron, job ou chamada externa e não implementa monitoramento de decay Diamante ou Flex.

## Jogadores adversários recorrentes

Em **Padrões**, além de Companheiros recorrentes, consulte **Adversários na sua posição** e **Adversários em todas as posições**. Cada lista mostra até dez jogadores, ordenados por partidas em comum, com suas vitórias/derrotas e taxa de vitória. A primeira compara a posição que você ocupou em cada jogo; a segunda inclui todos os jogadores do time adversário, mesmo de outras posições. O filtro Posição continua selecionando suas partidas, não limita a posição dos adversários na lista geral.

Temporada, período, fila e demais filtros da página se aplicam às duas listas. Os resultados dependem dos participantes já importados; resumos sem placar não permitem identificar jogadores. Posições desconhecidas ou duplicadas não são atribuídas arbitrariamente a um confronto direto. A taxa mede o resultado do jogo, não vitória na rota. Não há nova migration, job ou chamada à Riot para esse recurso; basta atualizar a página com o código e assets atuais.


## Estatísticas consolidadas no dashboard

A página inicial reúne os dados importados de todas as contas do usuário em sete abas, equivalentes às dos detalhes individuais. Use os filtros acima do resumo para escolher temporada, período, campeão, posição, adversário-campeão e resultado. A fila disponível é Solo/Duo. Os links entre gráficos, confrontos e histórico conservam o contexto global e os filtros. Os cards individuais continuam disponíveis abaixo das análises.

## Atualização: caderno, jogadores e limites — 17/10/2026

Aplicar `php artisan migrate --force` pelo procedimento de atualização, sem fresh/reset. A migration 000002 é aditiva e adiciona cobertura Solo e quatro tabelas privadas. Construir assets, limpar caches pelo procedimento normal e reiniciar workers persistentes. Flex deixa de ser opção; execuções antigas são canceladas quando processadas, sem remover o histórico existente. Cache compartilhado de produção deve ser database/Redis, nunca array: limites de requisições e cache precisam sobreviver entre processos.

O menu **Jogadores** e a busca no topo localizam jogadores do histórico importado, pelo nome/tag conhecido. Abra o resultado para ver partidas com/contra ele e escrever uma anotação privada. Os nomes dos jogadores no placar e em Padrões também abrem esse histórico. Anotações aparecem como indicador nas listas e no placar. Nome pode mudar; a anotação permanece no PUUID. Jogadores sem identidade/placar importado não aparecem. O caderno mantém as anotações existentes mesmo se a conta de origem for removida.

Em Partidas, **Anotar esta partida** permite salvar/remover texto privado (vazio remove). A anotação é a mesma nas participações das suas contas naquela partida. Em filtros, dê um nome e use **Salvar filtro favorito**. Acesse **Meu caderno** para aplicar/remover favoritos, criar/remover metas de partidas, vitórias ou taxa de vitória e consultar jogadores anotados. Metas somam contas em intervalo UTC, mostram a amostra e não incluem Flex, remakes ou legado não confirmado.

Limites configuráveis em workspace.php: 20 contas por usuário, três sincronizações simultâneas, 100 recentes/10 completas por dia UTC; 50 favoritos e 30 metas. O lote agenda até a capacidade disponível e informa o total; contas restantes podem ser solicitadas depois. O mesmo job ativo é reutilizado, sem gastar outra execução. Cadastro público: cinco tentativas/hora/IP e 30/hora na aplicação; recuperação: cinco/minuto/IP; redefinição: dez/minuto/IP. Resposta da recuperação não revela se o destinatário existe.

## Retenção, inventário e recuperação

Política atual: preservar indefinidamente partidas pessoais e cache referenciado; nenhuma limpeza automática. Cache sem referência com mais de 180 dias é apenas candidato a revisão, nunca excluído pelo comando. Anotações são dados pessoais privados e entram no backup. A retenção operacional de backups deve respeitar o orçamento e a política definida pelo proprietário; antes de qualquer descarte, manter cópia restaurável fora do servidor.

Executar `php artisan dashboard:storage-health` após importações amplas e ao menos semanalmente. Relata partidas, caches, tamanho aproximado dos payloads JSON e órfãos antigos. É somente leitura; o teste verifica que não exclui histórico/cache. O tamanho JSON não mede índices, participantes, logs, backups nem espaço físico do MySQL. Conferir também uso real no painel da hospedagem; configurar alerta do provedor antes de atingir a cota contratada. Não foi verificada a quota real do plano Business.

Procedimento de backup antes de atualização e periodicamente:

1. Confirmar um backup recente do banco inteiro e dos arquivos privados necessários (configuração/segredos em cofre separado, nunca no repositório). No painel/exportador MySQL, incluir schema, dados, índices e constraints de todas as tabelas. Para dump transacional, usar a opção single-transaction de ferramenta compatível com o servidor. Não informar senha na linha de comando nem versionar dumps.
2. Salvar cópia criptografada em local separado do servidor. Registrar data, tamanho e checksum; limitar quem pode ler notas/e-mails. Verificar o resultado do exportador, não apenas a existência do arquivo.
3. Restaurar em **outro banco e aplicação isolada**, nunca sobre o banco ativo. Usar credenciais/configuração próprias, bloquear saída de e-mail/Riot e suspender workers/scheduler nesse ambiente antes da restauração. Não reutilizar a chave de produção para testes.
4. Conferir migrations, contagens de usuários/contas/partidas/participantes/temporadas/notas/metas/favoritos, unicidade de partidas, uma temporada atual e integridade de chaves. Fazer login com usuário de teste, conferir histórico/placar/caderno e isolamento entre dois usuários. Comparar amostras com o backup.
5. Registrar duração e resultado da restauração; só então considerar o backup comprovado. Excluir a cópia de homologação pelo procedimento autorizado após a verificação. Uma restauração real na Hostinger permanece pendente; documentação e testes SQLite não comprovam essa operação.

A visão geral mostra combate, economia, visão, gráficos e totais de abates/mortes/assistências, além de totais aditivos junto às médias. Campeões, posições, padrões, companheiros e adversários recorrentes consideram todas as participações selecionadas. Partidas e recordes identificam a conta de origem. Histórico possui paginação de 20 participações.

Se duas contas suas estiverem na mesma partida, ambas contribuem para o consolidado: a unidade de contagem é participação por conta. Taxas e médias são recalculadas pelos dados de todas as partidas, sem somar porcentagens ou tirar média simples dos indicadores de cada conta. Métricas ausentes e resumos ainda sem detalhes podem limitar gráficos/indicadores. Na aba Evolução, resultados diários são consolidados e os gráficos de ranking ficam separados por conta; ranking e decay continuam com suas referências individuais.

Não há migração nem reinício de worker específico para esta mudança. Atualize o código/assets e recarregue o dashboard. Testes de navegador devem rodar depois dos testes PHP/compilação Blade para evitar disputa dos arquivos compilados no Windows.
