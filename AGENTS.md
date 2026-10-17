# Instruções permanentes para agentes

## Leitura obrigatória ao iniciar qualquer sessão

1. Leia este `AGENTS.md` e o relatório completo em [docs/DIARIO_DO_PROJETO.md](docs/DIARIO_DO_PROJETO.md) antes de alterar o projeto.
2. Confira `git status --short` e as diferenças dos arquivos envolvidos. Preserve alterações anteriores do usuário.
3. Confira instruções adicionais em diretórios que for modificar. Revalide no código os achados do diário: o documento é memória histórica, não substitui o estado atual.

## Atualização obrigatória do diário

Toda modificação no projeto deve ser acompanhada, na mesma entrega, de uma atualização em `docs/DIARIO_DO_PROJETO.md`. Isso inclui código, testes, dependências, configurações, interface, documentação e este arquivo.

- Acrescente uma entrada datada ao final do diário, com objetivo, alterações, arquivos, motivo, verificações, resultados, limitações e próximos passos.
- Registre cada alteração lógica realizada na sessão; uma entrada pode agrupar mudanças relacionadas, desde que todas fiquem identificadas.
- Atualize o estado dos achados afetados e as descrições de arquitetura ou operação que ficaram desatualizadas. Preserve o histórico; não apague entradas antigas para esconder problemas ou decisões.
- Só marque um problema como resolvido com evidência de verificação. Diferencie inspeção estática, teste automatizado, reprodução e hipótese.
- Se a sessão for apenas de análise, registre também os novos achados e as verificações executadas.
- Na resposta final, informe o que mudou e o resultado das verificações, inclusive falhas preexistentes e verificações não executadas.

## Contexto técnico

Aplicação Laravel 12 / PHP >= 8.2, Blade, Tailwind 4, Alpine e Vite. Autenticação Breeze com e-mail verificado. Leia também docs/ARQUITETURA.md e docs/OPERACAO.md. Controllers delegam alterações a app/Actions, leituras a app/Queries e rede a RiotService. SyncAccount executa etapas em fila; sync_runs guarda progresso, versão e próximo horário. Lock, prazo e scheduler coordenam recuperação.

## Regras de implementação e preservação

- Mantenha o isolamento por usuário: consultas de contas devem partir do usuário autenticado, ou passar por autorização equivalente. Cubra acessos de outro usuário com teste ao alterar esse fluxo.
- Não confie em opções do formulário como validação de servidor. Valide região por lista permitida e nunca permita que entrada do usuário determine livremente o host de chamadas autenticadas à Riot.
- Não imprima entrada do usuário, mensagens técnicas de exceção ou credenciais em HTML bruto. Use escaping e logs sem segredos.
- Leia variáveis de ambiente em `config/*`; serviços devem usar `config()`. Nunca copie valores de `.env` para relatórios, testes, respostas ou arquivos versionados.
- Preserve checkpoints atômicos e reagendamento versionado. Recuperação respeita available_at e lock; não volte a importar histórico dentro da requisição web.
- Legado sem identity_puuid deve ser preservado e mantido fora das métricas até confirmação pela API.
- Preserve a unicidade de partidas por `(match_id, conta_id)`. Considere PUUID, região, fila e temporada ao alterar identidade ou histórico; não apague histórico automaticamente sem uma decisão explícita sobre retenção.
- Diferencie conta sem ranking de falha externa. Não sobrescreva dados válidos por indisponibilidade da API.
- Integrações precisam de tentativas limitadas, tratamento dos status HTTP e controle de concorrência. Testes da integração devem usar `Http::fake()` ou mocks, sem consumir a chave real.
- Alterações de schema devem ter novas migrations quando já houver possibilidade de uso da migration anterior. Não execute `migrate:fresh`, resets, seeds ou rollback no banco do usuário para validar mudanças.
- Não atualize dependências indiscriminadamente durante tarefas sem relação. Os alertas registrados exigem triagem e validação de compatibilidade; não use `audit fix --force` automaticamente.
- Escreva documentação e registros em português, UTF-8. Priorize mudanças pequenas, justificadas pelo pedido e verificáveis.

## Verificação proporcional à mudança

- Backend: `php artisan test` (ou primeiro testes direcionados). A configuração versionada usa SQLite em memória; confira se não há configuração externa/cache redirecionando testes ao banco real.
- PHP: `php -l caminho/do/arquivo.php` nos arquivos alterados.
- Frontend: `npm.cmd run build` no PowerShell; use `npm run build` onde apropriado.
- Rotas: `php artisan route:list --except-vendor` quando afetadas.
- Blade: compilação com `php artisan view:cache`, seguida de `php artisan view:clear`, quando relevante; isso não substitui renderizar as páginas.
- Dependências: `composer.bat audit --format=json` e `npm.cmd audit --json` quando houver atualização ou revisão de segurança. O código de saída de uma auditoria com alertas pode ser não zero.
- As cinco falhas originais de perfil foram corrigidas. Preserve os testes e registre o resultado atual; a Entrada 001 continua como retrato histórico.
- JavaScript: npm.cmd test. Navegador: npm.cmd run test:browser, com Chromium instalado. Fixture exclusivamente em storage/framework/testing/browser.sqlite.
- Atualize também docs/ARQUITETURA.md e docs/OPERACAO.md quando houver mudanças de arquitetura ou operação.

## Modelo para nova entrada

```markdown
### AAAA-MM-DD — título da sessão
- Objetivo:
- Alterações e justificativa:
- Arquivos afetados:
- Verificações executadas e resultados:
- Achados atualizados (IDs e status):
- Limitações e pendências:
- Próximo passo recomendado:
```
