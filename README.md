# LoL Dashboard

Painel de contas de League of Legends com ranking Solo/Duo, histórico confirmado por PUUID e desempenho por campeão. Laravel 12, PHP 8.2+, Blade, Alpine, Tailwind 4 e Vite. Interface em português e responsiva.

## Comece por aqui

- Agentes: leia [AGENTS.md](AGENTS.md) e o [diário técnico](docs/DIARIO_DO_PROJETO.md) antes de alterar o projeto.
- Estrutura e decisões: [arquitetura](docs/ARQUITETURA.md).
- Instalação, atualização e produção: [operação](docs/OPERACAO.md).

## Instalação limpa

Requer PHP 8.2+ com extensões Laravel/PDO SQLite (ou MySQL/PostgreSQL), Composer 2 e Node 22.12+; validado com PHP 8.2 e Node 24. Navegadores modernos: Chrome 111+, Safari 16.4+, Firefox 128+ por causa do Tailwind 4.

~~~powershell
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate
npm.cmd ci
npm.cmd run build
~~~

A cópia do `.env`, a criação do SQLite e a geração de chave são apenas para instalação nova. Não sobrescreva configuração, banco ou chave de uma instalação existente.

Preencha `RIOT_KEY` no `.env`. A chave fica no servidor; não aparece em URLs ou no bundle. Configure um provedor de e-mail: o acesso ao painel exige confirmação de e-mail. O mailer `log` do exemplo é para desenvolvimento; o link pode ser consultado localmente em `storage/logs/laravel.log`.

~~~powershell
composer dev
~~~

O comando inicia servidor web, Vite, worker Riot e scheduler. Registre seu usuário no endereço exibido. A conexão da fila é definida por `RIOT_QUEUE_CONNECTION` (padrão `database`). Para promover um usuário existente, execute no console seguro:

~~~powershell
php artisan app:promote-admin seu-email@example.com
~~~

Não há credencial padrão ou administrador criado por seed.

## Uso

1. Adicione apelido, Riot ID (`Nome#TAG`) e região.
2. Sincronize para confirmar a identidade, consultar ranking e importar o histórico disponível.
3. Acompanhe o progresso nos detalhes; o painel continua utilizável durante o processamento.
4. Filtre 7, 30, 90, 365 dias ou todo o histórico confirmado.
5. Cancele uma execução se necessário. Nova sincronização consulta IDs recentes sem duplicar registros confirmados.

Ranking atual e estatísticas do período são métricas diferentes. O total mede participações: duas contas na mesma partida contam como duas participações. Registros antigos sem identidade confirmada são preservados e ficam fora das métricas até serem reencontrados na API. O painel não calcula reserva real de decay; os cortes exibidos são referências consultadas e não garantem promoção.

## Qualidade

~~~powershell
php artisan test
php vendor/bin/pint --test
npm.cmd test
npm.cmd run build
composer audit
npm.cmd audit
~~~

Testes PHP usam SQLite em memória e bloqueiam HTTP não simulado. Para testes de navegador:

~~~powershell
npx playwright install chromium
npm.cmd run test:browser
~~~

Eles criam exclusivamente `storage/framework/testing/browser.sqlite`, com usuários fictícios e chave de teste; recusam banco diferente. Não rode workers sobre esse banco. Pode usar Chrome instalado definindo `PLAYWRIGHT_BROWSER_EXECUTABLE`. Screenshots ficam em `storage/framework/testing/screenshots/`, ignorado pelo Git.

CI executa testes PHP/JavaScript/navegador, Pint, build, cache de rotas e auditorias. Os JSONs em `docs/auditorias/` são retratos históricos, não garantias de ausência de avisos futuros.

## Segurança e suporte

Usuários acessam apenas suas contas. Moderadores consultam usuários; administradores alteram cargos user/mod e podem excluir usuários não administrativos, com auditoria. Administradores não podem ser removidos/rebaixados pelo painel; o último admin não pode excluir o próprio perfil.

Use cache centralizado quando houver múltiplos workers. Leia [operação](docs/OPERACAO.md) antes de publicar.

LoL Dashboard não é endossado pela Riot Games. League of Legends e Riot Games são marcas de seus respectivos titulares.

### Análise pessoal e partidas

Nos detalhes da conta, use as abas para consultar histórico com placar dos dois times, campeões, builds, runas, objetivos, métricas de farm/dano/visão, padrões, recordes e evolução observada da liga. Filtros incluem período, fila Solo/Duo ou Flex, campeão, adversário, posição e resultado. A barra de campeões resume os últimos 20 jogos do recorte.

A sincronização comum prioriza até 60 jogos recentes. Para detalhes antigos, selecione a temporada e use sua opção de importação. Não são necessárias consultas individuais aos outros jogadores nem uma base externa de médias. Consulte docs/OPERACAO.md para operação da fila e atualização do catálogo público com npm.cmd run assets:update.

### Progresso até o próximo elo

Dashboard e detalhes exibem barras de progresso Solo/Duo. Ferro–Diamante usam divisão e LP; Mestre+ usam cortes independentes de Grão-Mestre e Desafiante, consultados diariamente no Replays.lol. A fonte/data são exibidas e falhas preservam o último valor com aviso. O scheduler atualiza às 03:10 de Brasília. Para atualização inicial/manual: php artisan riot:cutoffs; consulte OPERACAO.md antes de usar --force.

### Interface e confrontos

O painel usa tema roxo escuro e dourado, com gráficos de resultados diários, taxa de vitória e evolução observada do ranking. Na aba Confrontos, consulte os adversários por posição com retratos dos campeões e abra as partidas correspondentes. O histórico possui prévia dos times alinhados por rota e visão compacta por padrão. Os gráficos usam o próprio histórico, sem novas chamadas autenticadas à Riot.


## Temporadas

2026 já vem cadastrada como atual. Administradores podem cadastrar próximas temporadas em **Temporadas** e iniciar uma delas para encerrar a anterior no mesmo instante. O histórico é preservado; filtros no dashboard e na conta permitem consultar cada temporada. Selecione “Toda a temporada” para remover o limite móvel de dias. Sincronizações respeitam o intervalo selecionado e o ranking exibido permanece o atual da Riot.
