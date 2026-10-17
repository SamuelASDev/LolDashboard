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
