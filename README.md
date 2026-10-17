# LoL Dashboard

Painel para organizar contas de **League of Legends** e acompanhar ranking Solo/Duo, histórico de partidas e desempenho pessoal. Reúne as estatísticas de todas as contas do usuário e permite analisar cada uma separadamente.

A aplicação tem interface em português, tema escuro e layout responsivo. A sincronização acontece em fila: é possível continuar usando o dashboard enquanto o servidor consulta a Riot e importa as partidas.

Desenvolvido por [SamuelASDev](https://github.com/SamuelASDev). Código disponibilizado sob a [licença MIT](LICENSE).

## Índice

- [Funcionalidades](#funcionalidades)
- [Significado dos dados](#significado-dos-dados)
- [Tecnologias e requisitos](#tecnologias-e-requisitos)
- [Instalação local](#instalação-local)
- [Configuração](#configuração)
- [Primeiros passos](#primeiros-passos)
- [Sincronização e processos](#sincronização-e-processos)
- [Administração](#administração)
- [Estrutura do projeto](#estrutura-do-projeto)
- [Testes e qualidade](#testes-e-qualidade)
- [Publicação e atualização](#publicação-e-atualização)
- [Solução de problemas](#solução-de-problemas)
- [Contribuição e documentação](#contribuição-e-documentação)
- [Licença e créditos](#licença-e-créditos)

## Funcionalidades

### Contas e dashboard

- Cadastro de várias contas por Riot ID (`Nome#TAG`) e região, sem solicitar senha da Riot.
- Confirmação da identidade por PUUID durante a sincronização.
- Ranking atual Solo/Duo, LP, vitórias e derrotas da liga.
- Dashboard consolidado das contas do usuário e páginas de análise individual.
- Abas **Visão geral**, **Partidas**, **Campeões**, **Confrontos**, **Padrões**, **Recordes** e **Evolução**.
- Filtros por temporada, período, campeão, posição, campeão adversário e resultado.
- Períodos de 7, 30, 90 e 365 dias, além de toda a temporada selecionada.

### Partidas e desempenho

O histórico paginado identifica a conta de origem no consolidado. Cada partida pode mostrar placar dos dois times, jogadores, campeões, K/D/A, farm, dano, ouro, visão, build, feitiços e runas, conforme os dados importados. A visão compacta é o padrão; também há uma prévia dos times organizada por posição.

As análises incluem resultados por campeão e posição, confrontos por posição, padrões de resultado, recordes e gráficos. O histórico observado do ranking é separado por conta: não há reconstrução de LP de partidas anteriores.

### Jogadores e caderno pessoal

- Companheiros recorrentes e adversários mais enfrentados, separando mesma posição e todas as posições.
- Clique no jogador para abrir suas partidas com ou contra ele.
- Pesquisa por nome ou tag entre jogadores encontrados no histórico importado.
- Anotações privadas de jogadores vinculadas ao PUUID, preservadas quando o nome muda.
- Anotações privadas de partidas, com indicador no histórico.
- Filtros favoritos para reutilizar recortes do dashboard ou de uma conta.
- Metas de partidas jogadas, vitórias e taxa de vitória, com intervalo, progresso e tamanho da amostra.

### Ranking, temporadas e decay

O painel mostra progresso até o próximo elo por divisão/LP e referências de corte para Mestre+. Os cortes de Grão-Mestre e Desafiante são consultados no Replays.lol por uma tarefa agendada, com origem, atualização e aviso de indisponibilidade.

Administradores podem cadastrar e iniciar temporadas sem apagar as partidas anteriores. A migration inicial do catálogo cadastra 2026 como atual. A troca encerra a anterior no mesmo instante; o início da partida determina seu período. O ranking exibido continua sendo o atual, mesmo ao consultar uma temporada encerrada.

O acompanhamento **estimado de decay monitora somente Mestre, Grão-Mestre e Desafiante**. Mostra reserva, tempo restante, alertas e possível reserva esgotada. É possível informar uma referência manual de 0 a 14 dias, conforme o cliente do jogo.

### Autenticação e isolamento

Há cadastro, login, confirmação de e-mail, recuperação de senha e gerenciamento de perfil. Contas, histórico e anotações são acessados conforme o usuário autenticado. A administração distingue usuário, moderador e administrador.

O backend valida regiões, usa hosts fixos para a Riot e aplica controles de concorrência, limites de recursos e tentativas limitadas.

**Somente Solo/Duo está disponível.** Flex foi removido da interface e das novas sincronizações. Registros Flex já armazenados são preservados, sem entrar nas análises atuais.

## Significado dos dados

A unidade do consolidado é **participação de conta**. Se duas contas suas estiverem na mesma partida, ela contribui duas participações. Resultados e métricas consideram a perspectiva de cada conta; porcentagens são recalculadas a partir dos totais, sem somar taxas de vitória individuais.

Ranking atual e histórico filtrado têm origens diferentes. Seus totais podem divergir por período, temporada, disponibilidade da API e importação incompleta. O painel não calcula MMR, não comprova vitória de rota e não utiliza uma base externa de médias de outros jogadores.

Registros antigos sem identidade confirmada são preservados e ficam fora das métricas até confirmação pela API. Remakes identificados pela regra de importação são excluídos dos indicadores. Métricas ausentes permanecem indisponíveis, em vez de virarem zero.

O decay é uma projeção local: a integração não fornece o saldo oficial de dias nem confirma que uma perda de LP foi causada por inatividade. Sem referência manual, o cálculo trabalha com um intervalo de reserva e os jogos detalhados disponíveis. Ele não desconta LP da conta. Importar uma temporada antiga não atualiza a cobertura Solo usada pelo aviso de decay.

## Tecnologias e requisitos

| Área | Tecnologia |
| --- | --- |
| Backend | PHP 8.2+ e Laravel 12 |
| Interface | Blade, Alpine.js, Tailwind CSS 4 e Vite |
| Autenticação | Laravel Breeze e verificação de e-mail |
| Banco | SQLite local e nos testes; MySQL também validado localmente |
| Processamento | Jobs Laravel, fila assíncrona, locks e scheduler |
| Testes | PHPUnit, testes JavaScript com Node e Playwright |

Instale **Composer 2**, **Node.js 22.12+**, npm e as extensões PHP exigidas pelo Laravel. Habilite o driver PDO do banco escolhido; os testes PHP exigem SQLite (`pdo_sqlite`). O projeto foi exercitado com PHP 8.2 e Node 24.

Uma chave Riot válida é necessária para sincronizar contas reais. A interface pode ser acessada sem consumir a API, mas novas estatísticas dependem da importação. Configure também e-mail para confirmar usuários e recuperar senhas.

## Instalação local

Para uma **instalação nova**, no PowerShell do Windows:

```powershell
git clone https://github.com/SamuelASDev/LolDashboard.git
cd LolDashboard
git switch dev
composer install
Copy-Item .env.example .env
php artisan key:generate
New-Item -ItemType File database/database.sqlite
php artisan migrate
npm.cmd ci
npm.cmd run build
```

No Linux/macOS, use `cp .env.example .env`, `touch database/database.sqlite` e `npm` no lugar de `npm.cmd`. O exemplo usa SQLite; para MySQL, configure a conexão antes das migrations.

Preencha a chave Riot e as configurações necessárias no `.env`, conforme a próxima seção, e inicie:

```shell
composer dev
```

Esse comando inicia **servidor web Laravel, Vite, worker Riot e scheduler**. Abra o endereço informado pelo servidor, normalmente `http://127.0.0.1:8000`.

O terminal precisa permanecer aberto durante o desenvolvimento. Encerrar o comando encerra seus processos. Ao alterar jobs ou configuração, encerre e inicie novamente o `composer dev` para o worker carregar a versão atual.

Em uma instalação existente, preserve `.env`, banco e `APP_KEY`. Não repita a criação do banco, não gere outra chave e não use `migrate:fresh` para atualizar o projeto.

## Configuração

O [.env.example](.env.example) documenta os nomes das variáveis. Credenciais reais ficam somente no ambiente do servidor.

| Variável | Finalidade |
| --- | --- |
| `APP_URL` | URL pública, também usada por links gerados em console/filas |
| `APP_ENV` / `APP_DEBUG` | Em produção: `production` e `false` |
| `APP_KEY` | Chave da instalação, preservada nas atualizações |
| `DB_CONNECTION` / `DB_*` | Conexão e credenciais do banco |
| `RIOT_KEY` | Chave Riot usada exclusivamente no servidor |
| `RIOT_QUEUE_CONNECTION` | Conexão da fila Riot; padrão `database` |
| `RIOT_REQUESTS_PER_SECOND` | Orçamento local de curto prazo; padrão 15 |
| `RIOT_REQUESTS_PER_TWO_MINUTES` | Orçamento local compartilhado de 120 segundos; padrão 80 |
| `DB_QUEUE_RETRY_AFTER` | Prazo de retomada da fila database; padrão 180 segundos |
| `CACHE_STORE` | Cache compartilhado de locks, limites e agregados; exemplo usa `database` |
| `SESSION_DRIVER` | Armazenamento de sessões; exemplo usa `database` |
| `MAIL_*` | Transporte e remetente para verificação e recuperação |
| `SESSION_SECURE_COOKIE` | Habilitar em produção com HTTPS |

O mailer `log` é para desenvolvimento: o link de verificação aparece localmente em `storage/logs/laravel.log`. Em produção, configure entrega real. Promover um usuário a administrador não dispensa a confirmação de e-mail.

Ajuste os orçamentos de chamadas à chave utilizada. O controle local não substitui os limites da Riot. Falhas e respostas de limitação têm tratamento próprio; aumentar os valores não resolve uma restrição externa.

Os limites do produto ficam em [config/workspace.php](config/workspace.php):

| Recurso | Padrão |
| --- | --- |
| Contas por usuário | 20 |
| Sincronizações ativas por usuário | 3 |
| Buscas recentes por usuário/dia UTC | 100 |
| Importações completas por usuário/dia UTC | 10 |
| Filtros favoritos por usuário | 50 |
| Metas por usuário | 30 |

Esses valores são configuração de aplicação, não variáveis `.env`. Cache `array` fica reservado aos testes; os processos de operação precisam compartilhar armazenamento e namespace de cache.

## Primeiros passos

1. Cadastre seu usuário e confirme o e-mail.
2. Adicione uma conta com apelido, Riot ID completo e região.
3. Clique em **Sincronizar conta** para confirmar identidade e importar dados.
4. Acompanhe o progresso nos detalhes, mantendo worker e scheduler ativos.
5. Explore o dashboard consolidado ou abra uma conta para análise individual.
6. Para ampliar o histórico, selecione a temporada e use **Importação da temporada**.
7. Em **Padrões** ou no placar, clique em um jogador para ver os encontros e anotar.
8. Acesse **Meu caderno** para consultar favoritos, jogadores anotados e metas.

Regiões disponíveis: Brasil (`br1`), América do Norte (`na1`), Europa Ocidental (`euw1`), Europa Nórdica e Oriental (`eun1`), Coreia (`kr`) e Japão (`jp1`).

## Sincronização e processos

A requisição web agenda a execução; o worker avança por etapas limitadas. O servidor resolve a identidade, consulta ranking, busca IDs e importa detalhes com checkpoints. A unicidade por partida/conta evita duplicar a mesma participação.

A busca recente verifica até 60 IDs na configuração atual. A importação da temporada percorre uma janela maior, sujeita a paginação, cotas, prazo e disponibilidade da Riot. Conclusão significa fim da busca conforme essas regras; não garante histórico completo desde a criação da conta.

Cancelar preserva os dados confirmados. Uma execução já ativa é reutilizada. **Sincronizar todas** agenda o que couber nos limites e informa a quantidade; as demais contas podem ser solicitadas depois.

Para iniciar os processos separadamente, com a conexão padrão:

```shell
php artisan serve
php artisan queue:work database --queue=riot --sleep=1 --tries=3 --timeout=90
php artisan schedule:work
npm run dev
```

Cada comando ocupa seu próprio terminal. No PowerShell, use `npm.cmd run dev`. Se mudar a conexão da fila Riot, ajuste também o argumento do worker.

Para hospedagem com cron e sem worker permanente, use `scripts/cron-worker.php`: ele inicia um worker temporário e evita sobreposição com o lock nativo do PHP, sem depender do comando shell flock. Configure sua chamada a cada minuto e mantenha outra tarefa para `artisan schedule:run`. Use `php scripts/cron-worker.php --check` para verificar bootstrap/configuração/lock sem consumir jobs. Consulte [operação](docs/OPERACAO.md) para preenchimento no hPanel e limitações.

| Comando | Uso |
| --- | --- |
| `php artisan riot:recover` | Recuperar etapas paradas, respeitando locks e próximo horário permitido |
| `php artisan riot:cutoffs` | Atualizar/reutilizar as referências de corte do dia |
| `php artisan queue:failed` | Consultar falhas da infraestrutura de fila |
| `php artisan dashboard:storage-health` | Relatar volume/cache antigo sem referência, sem apagar dados |
| `npm.cmd run assets:update` | Atualizar o catálogo público e revisar o JSON resultante |

O scheduler agenda recuperação a cada cinco minutos e cortes às 03:10 de Brasília. O catálogo usa o CDN público Data Dragon, sem consumir a chave Riot. Ícones atuais não reconstroem atributos históricos dos itens por patch.

## Administração

Não há senha padrão nem administrador criado automaticamente. Cadastre e verifique um usuário e execute em console autorizado do servidor:

```shell
php artisan app:promote-admin seu-email@example.com
```

Usuários gerenciam seus próprios dados. Moderadores consultam a listagem de usuários; administradores também alteram cargos `user`/`mod`, excluem usuários permitidos e gerenciam temporadas. A administração não abre os históricos privados de outras contas pelo dashboard.

Administradores são protegidos contra remoção/rebaixamento pelo painel, e o último administrador não pode excluir o próprio perfil. Exclusão é definitiva e remove dados vinculados; não equivale a banimento. As alterações administrativas previstas no sistema são auditadas.

## Estrutura do projeto

```text
app/
  Actions/           Escritas e operações de domínio
  Console/Commands/  Administração, recuperação, cortes e inventário
  Enums/             Regiões permitidas
  Http/              Controllers, requests e middleware
  Jobs/              Etapas da sincronização em fila
  Models/            Entidades e relacionamentos
  Queries/           Leituras e agregados autorizados
  Services/          Integração Riot
  Support/           Janelas de histórico, gráficos e apresentação
config/              Configuração e limites
database/migrations/ Evolução aditiva do schema
resources/
  views/             Páginas e componentes Blade
  css/               Estilos e Tailwind
  js/                Alpine e acompanhamento da sincronização
  data/              Catálogo público de assets
routes/              Web, autenticação e agenda
scripts/             Inicialização local e atualização de assets
tests/               PHP, JavaScript e navegador
docs/                Documentação de operação
```

O cache compartilhado evita baixar novamente uma partida já importada. Projeções pessoais continuam vinculadas à conta; anotações pertencem ao usuário. Não existe rota pública para consultar livremente o cache por ID.

## Testes e qualidade

Com as dependências de desenvolvimento instaladas:

```powershell
php artisan test
php vendor/bin/pint --test
npm.cmd test
npm.cmd run build
composer validate --strict
composer audit
npm.cmd audit
```

A suíte PHP usa SQLite em memória, cache/sessão de teste e respostas simuladas de integrações. Confira antes se configuração compilada ou variáveis externas não estão direcionando testes ao banco real. Não utilize uma chave Riot real nos testes.

Para navegador:

```powershell
npx playwright install chromium
npm.cmd run build
npm.cmd run test:browser
```

O Playwright prepara exclusivamente `storage/framework/testing/browser.sqlite`, com usuários fictícios, e recusa outro banco. Não execute workers sobre essa fixture. É possível apontar um navegador instalado com `PLAYWRIGHT_BROWSER_EXECUTABLE`. Capturas ficam em `storage/framework/testing/screenshots/`, fora do Git.

Execute testes PHP e compilação/limpeza Blade antes dos testes de navegador, sem concorrência entre eles: no Windows, os processos podem disputar views compiladas. Os testes usam o build, isolado do arquivo hot do Vite local.

O workflow [Quality](.github/workflows/ci.yml) está configurado para pushes em `dev`/`main` e pull requests: testes, build, Pint, cache de rotas e auditorias.

## Publicação e atualização

`composer dev`, `artisan serve`, Vite de desenvolvimento e um terminal pessoal aberto não compõem o ambiente de produção.

Uma publicação precisa de:

- PHP compatível, HTTPS e raiz web apontada somente para `public/`.
- `.env` de produção, `APP_DEBUG=false`, banco, e-mail real e chave Riot no servidor.
- Dependências PHP de `composer.lock`, instaladas com `--no-dev`.
- Assets gerados de `package-lock.json`, com `public/build` disponível no servidor.
- Permissão de escrita adequada em `storage/` e `bootstrap/cache/`.
- Fila Riot e scheduler executados pelo mecanismo da hospedagem.
- Backup restaurável antes das migrations e reinício dos processos após o deploy.

`public/build` não é versionado: gere no processo de publicação ou envie o artefato. Apenas executar `git pull` não fornece esses assets.

Em atualizações, preserve dados, `.env` e `APP_KEY`; instale os lockfiles, gere assets, aplique `php artisan migrate --force`, atualize caches e reinicie workers pelo processo normal. `queue:restart` sinaliza encerramento e depende de reinício correspondente; sem supervisor, não mantém um worker novo funcionando.

Em hospedagem compartilhada, incluindo a configuração planejada para Hostinger Business, confirme os recursos reais de PHP, SSH, cron, caminhos e limites da conta. A execução da fila precisa ser adaptada e homologada nesse ambiente. **Não há deploy automático para a Hostinger configurado neste repositório.** Em servidor com supervisão, mantenha worker supervisionado e execute `php artisan schedule:run` a cada minuto por cron.

Não há limpeza automática de histórico/cache. O inventário não mede sozinho espaço físico, índices, logs ou backups. Consulte o procedimento de backup/restauração e os detalhes em [docs/OPERACAO.md](docs/OPERACAO.md).

## Solução de problemas

| Sintoma | Conferir |
| --- | --- |
| Sincronização aguardando | Worker na conexão correta e na fila `riot`; processo ativo |
| Job não carrega código novo | Reiniciar `composer dev` ou os workers pelo mecanismo de produção |
| Dashboard exige confirmação | Verificação de e-mail e entrega; mailer log somente local |
| Estatísticas incompletas | Temporada, filtros, identidade, disponibilidade de detalhes e importação ampliada |
| Decay desatualizado | Ranking e cobertura Solo atuais; importar temporada antiga não renova essa cobertura |
| Execução não agendada | Cotas, tarefas ativas, validade da chave e limites da Riot |
| Manifesto/assets ausentes | Build e `public/build`; não enviar `public/hot` de desenvolvimento |
| Destino antigo no login | O app descarta conta/destino inacessível; conferir também `APP_URL` |

Consulte os logs sem publicar credenciais, links privados de verificação ou dados de usuários em issues.

## Contribuição e documentação

Use `dev` para desenvolvimento e `main` para a versão integrada. Faça mudanças pequenas, descreva a motivação e execute verificações proporcionais ao que mudou.

Antes de alterar, consulte a documentação de operação, preserve o isolamento por usuário e simule integrações nos testes. Não versione `.env`, chaves, dumps, dados pessoais, `vendor/`, `node_modules/` ou fixtures geradas.

| Documento | Conteúdo |
| --- | --- |
| [Operação](docs/OPERACAO.md) | Instalação, atualização, configuração e recuperação |

## Licença e créditos

O código deste projeto é distribuído sob a **licença MIT**. Texto integral e copyright em [LICENSE](LICENSE). Dependências e materiais de terceiros mantêm suas próprias licenças e condições de uso.

Dados de partidas/ranking vêm das integrações Riot; o catálogo de ícones usa Data Dragon e as referências de corte usam Replays.lol. A licença do projeto não concede direitos sobre marcas e conteúdo de terceiros.

**LoL Dashboard não é endossado pela Riot Games e não reflete suas opiniões. League of Legends e Riot Games são marcas de seus respectivos titulares.**
