import { test, expect } from '@playwright/test';
import fs from 'node:fs';

async function login(page) {
    await page.goto('/login');
    await page.locator('#email').fill('browser@example.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page).toHaveURL(/dashboard/);
}
async function openMenu(page, project) {
    if (project === 'mobile') await page.getByRole('button', { name: 'Menu', exact: true }).click();
}

test('dashboard, details, profile and administration render without JS errors', async ({ page }, testInfo) => {
    const errors = [];
    page.on('pageerror', error => errors.push(error.message));
    await login(page);
    await expect(page.getByRole('heading', { name: 'Visão geral', exact: true })).toBeVisible();
    await expect(page.getByText('DIAMOND II')).toBeVisible();
    await expect(page.getByRole('progressbar', { name: 'Próximo elo: Mestre', exact: true })).toHaveAttribute('aria-valuenow', '263');
    await expect(page.getByRole('rowheader', { name: 'Ahri', exact: true })).toBeVisible();
    if (testInfo.project.name === 'mobile') {
        await expect.poll(async () => page.locator('#sidebar').evaluate(el => Math.round(el.getBoundingClientRect().right))).toBeLessThanOrEqual(0);
    }
    const overflow = await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth);
    expect(overflow).toBe(false);
    fs.mkdirSync('storage/framework/testing/screenshots', { recursive: true });
    await page.screenshot({ path: 'storage/framework/testing/screenshots/dashboard-'+testInfo.project.name+'.png', fullPage: true });
    await page.getByRole('link', { name: 'Ver detalhes' }).click();
    await expect(page.getByRole('heading', { name: 'Partidas confirmadas', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Partidas', exact: true }).click();
    await page.getByText('Ver jogadores e placar', { exact: true }).click();
    await expect(page.locator('table').getByText('Jogador 10#BR1', { exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await page.screenshot({ path: 'storage/framework/testing/screenshots/details-'+testInfo.project.name+'.png', fullPage: true });
    await expect(page.locator('.match-lineup-preview')).toBeHidden();
    await page.getByRole('button', { name: 'Visão completa', exact: true }).click();
    await expect(page.locator('.match-lineup-preview')).toBeVisible();
    await page.getByRole('button', { name: 'Visão compacta', exact: true }).click();
    await expect(page.locator('.match-lineup-preview')).toBeHidden();
    await expect(page.locator('table').getByText('Jogador 10#BR1', { exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Visão completa', exact: true }).click();
    for (const tab of ['Campeões', 'Confrontos', 'Padrões', 'Recordes', 'Evolução']) {
        await page.getByRole('link', { name: tab, exact: true }).click();
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    }
    await openMenu(page, testInfo.project.name);
    await page.getByRole('link', { name: 'Meu perfil', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Meu perfil', exact: true })).toBeVisible();
    await openMenu(page, testInfo.project.name);
    await page.getByRole('link', { name: 'Usuários', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Usuários', exact: true })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/screenshots/admin-'+testInfo.project.name+'.png', fullPage: true });
    expect(errors).toEqual([]);
});

test('validation feedback retains input and synchronization can be queued and cancelled', async ({ page }, testInfo) => {
    await login(page);
    await page.getByRole('link', { name: '+ Adicionar conta', exact: true }).click();
    await page.locator('#nick').fill('Meu cadastro');
    await page.locator('#riot_id').fill('SemTag');
    await page.locator('#regiao').selectOption('br1');
    await page.getByRole('button', { name: 'Adicionar conta', exact: true }).click();
    await expect(page.getByText('Informe um Riot ID válido no formato Nome#TAG.').first()).toBeVisible();
    await expect(page.locator('#nick')).toHaveValue('Meu cadastro');
    await page.goto('/contas/1');
    await page.getByRole('button', { name: 'Sincronizar conta', exact: true }).click();
    await expect(page.getByRole('heading', { name: /Sincronização/ })).toBeVisible();
    await expect(page.getByRole('button', { name: 'Cancelar', exact: true })).toBeVisible();
    await page.getByRole('button', { name: 'Cancelar', exact: true }).click();
    await expect(page.getByText('Cancelada pelo usuário. Dados confirmados preservados.')).toBeVisible();
});

test('login discards a deleted account destination and account list remains reachable', async ({ page }) => {
    await page.goto('/contas/999999');
    await expect(page).toHaveURL(/\/login$/);
    await login(page);
    await page.goto('/contas');
    await expect(page).toHaveURL(/\/dashboard$/);
    await expect(page.getByRole('heading', { name: 'Visão geral', exact: true })).toBeVisible();
});

test('registration and subsequent login reach email verification without a broken redirect', async ({ page }, testInfo) => {
    const email = 'registration-' + testInfo.project.name + '@example.test';
    await page.goto('/contas/999999');
    await page.goto('/register');
    await page.locator('#name').fill('Novo jogador de teste');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.locator('#password_confirmation').fill('password');
    await page.getByRole('button', { name: 'Criar conta', exact: true }).click();
    await expect(page).toHaveURL(/\/verify-email$/);
    await expect(page.getByText(/Antes de acessar o painel/)).toBeVisible();
    await page.getByRole('button', { name: 'Sair', exact: true }).click();
    await page.goto('/login');
    await page.locator('#email').fill(email);
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page).toHaveURL(/\/verify-email$/);
    await expect(page.getByRole('button', { name: 'Reenviar e-mail de confirmação', exact: true })).toBeVisible();
});

test('master shows only the next rank cutoff without horizontal overflow', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.locator('#email').fill('apex@example.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('progressbar', { name: 'Corte Grão-Mestre', exact: true })).toHaveAttribute('aria-valuenow', '1400');
    await expect(page.getByRole('progressbar')).toHaveCount(1);
    await expect(page.getByText('Corte Desafiante', { exact: true })).toHaveCount(0);
    await expect(page.getByText(/100 LP acima/)).toBeVisible();
    await expect(page.getByText(/@else/)).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    fs.mkdirSync('storage/framework/testing/screenshots', { recursive: true });
    await page.screenshot({ path: 'storage/framework/testing/screenshots/rank-progress-'+testInfo.project.name+'.png', fullPage: true });
    await page.getByRole('link', { name: 'Ver detalhes' }).click();
    await expect(page.getByRole('progressbar', { name: 'Corte Grão-Mestre', exact: true })).toBeVisible();
});

test('decay reserve can be calibrated and warns when empty', async ({ page }, testInfo) => {
    await page.goto('/login');
    await page.locator('#email').fill('apex@example.test');
    await page.locator('#password').fill('password');
    await page.getByRole('button', { name: 'Entrar', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Decay Solo/Duo', exact: true })).toBeVisible();
    await page.getByRole('link', { name: 'Conferir e ajustar reserva', exact: true }).click();
    await page.getByText('Ajustar reserva de decay pelo cliente', { exact: true }).click();
    await page.getByLabel('Dias restantes agora (0 a 14)').fill('5');
    await page.getByRole('button', { name: 'Salvar reserva atual', exact: true }).click();
    await expect(page.getByText('Proteção estimada disponível', { exact: true })).toBeVisible();
    await expect(page.getByRole('progressbar', { name: 'Reserva estimada de decay', exact: true })).toHaveAttribute('aria-valuenow', '5');
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await page.screenshot({ path: 'storage/framework/testing/screenshots/decay-'+testInfo.project.name+'.png', fullPage: true });
    await page.getByText('Ajustar reserva de decay pelo cliente', { exact: true }).click();
    await page.getByLabel('Dias restantes agora (0 a 14)').fill('0');
    await page.getByRole('button', { name: 'Salvar reserva atual', exact: true }).click();
    await expect(page.getByText('Possível decay em andamento', { exact: true })).toBeVisible();
    await expect(page.getByText(/Perda real de LP não confirmada/)).toBeVisible();
    await page.getByText('Ajustar reserva de decay pelo cliente', { exact: true }).click();
    await page.getByRole('button', { name: 'Voltar à estimativa automática', exact: true }).click();
    await expect(page.getByText('Estimativa automática', { exact: true })).toBeVisible();
});

test('role matchups and charts remain usable on every viewport', async ({ page }, testInfo) => {
    await login(page);
    await page.getByRole('link', { name: 'Ver detalhes' }).click();
    await expect(page.getByRole('heading', { name: 'Ritmo de jogo', exact: true })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/screenshots/overview-redesign-'+testInfo.project.name+'.png', fullPage: true });
    if (testInfo.project.name === 'desktop') await page.screenshot({ path: 'storage/framework/testing/screenshots/overview-redesign-preview.png' });
    await page.getByRole('link', { name: 'Confrontos', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Confrontos por posição', exact: true })).toBeVisible();
    await page.locator('[aria-label="Posições dos confrontos"]').getByRole('link', { name: 'Topo', exact: true }).click();
    await expect(page).toHaveURL(/role=TOP/);
    await expect(page.getByRole('region', { name: 'Confrontos: Topo', exact: true })).toBeVisible();
    await expect(page.getByRole('region', { name: 'Confrontos: Meio', exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await page.screenshot({ path: 'storage/framework/testing/screenshots/matchups-redesign-'+testInfo.project.name+'.png', fullPage: true });
    await page.getByRole('link', { name: 'Evolução', exact: true }).click();
    await expect(page.getByRole('img', { name: /Evolução da classificação/ })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/screenshots/charts-redesign-'+testInfo.project.name+'.png', fullPage: true });
});

test('patterns separates opponents in our position from the entire enemy team', async ({ page }, testInfo) => {
    await login(page);
    await page.goto('/contas/1?tab=patterns');
    await expect(page.getByRole('heading', { name: 'Companheiros recorrentes', exact: true })).toBeVisible();
    const lane = page.getByRole('region', { name: 'Adversários na sua posição', exact: true });
    const all = page.getByRole('region', { name: 'Adversários em todas as posições', exact: true });
    await expect(lane.getByText('Jogador 6#BR1', { exact: true })).toBeVisible();
    await expect(lane.getByText('Jogador 10#BR1', { exact: true })).toHaveCount(0);
    await expect(all.getByText('Jogador 6#BR1', { exact: true })).toBeVisible();
    await expect(all.getByText('Jogador 10#BR1', { exact: true })).toBeVisible();
    await expect(all.getByText('Jogador 2#BR1', { exact: true })).toHaveCount(0);
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await lane.locator('..').screenshot({ path: 'storage/framework/testing/screenshots/recurring-opponents-'+testInfo.project.name+'.png' });
    const role = page.locator('select[name="role"]');
    if (!await role.isVisible()) await page.locator('details').filter({ has: role }).locator('summary').first().click();
    await role.selectOption('TOP');
    await page.getByRole('button', { name: 'Aplicar filtros', exact: true }).click();
    await expect(lane.getByText('Sem adversários identificados neste recorte.', { exact: true })).toBeVisible();
    await expect(all.getByText('Sem adversários identificados neste recorte.', { exact: true })).toBeVisible();
});

test('dashboard offers consolidated analytics and keeps navigation in the global scope', async ({ page }, testInfo) => {
    await login(page);
    const tabs = page.getByRole('navigation', { name: 'Estatísticas de todas as contas', exact: true });
    await expect(page.getByRole('heading', { name: 'Combate', exact: true })).toBeVisible();
    await expect(page.getByRole('heading', { name: 'Economia', exact: true })).toBeVisible();
    await page.screenshot({ path: 'storage/framework/testing/screenshots/dashboard-consolidated-'+testInfo.project.name+'.png', fullPage: true });
    for (const tab of ['Partidas', 'Campeões', 'Confrontos', 'Padrões', 'Recordes', 'Evolução']) {
        await tabs.getByRole('link', { name: tab, exact: true }).click();
        await expect(page).toHaveURL(/\/dashboard\?/);
        expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    }
    await expect(page.getByRole('heading', { name: 'Evolução de ranking por conta', exact: true })).toBeVisible();
    await tabs.getByRole('link', { name: 'Padrões', exact: true }).click();
    await expect(page.getByRole('region', { name: 'Adversários em todas as posições', exact: true }).getByText('Jogador 10#BR1', { exact: true })).toBeVisible();
    await tabs.getByRole('link', { name: 'Confrontos', exact: true }).click();
    await page.locator('[aria-label="Posições dos confrontos"]').getByRole('link', { name: 'Meio', exact: true }).click();
    await page.getByRole('region', { name: 'Confrontos: Meio', exact: true }).getByRole('link').first().click();
    await expect(page).toHaveURL(/\/dashboard\?.*tab=history/);
    await expect(page).toHaveURL(/role=MIDDLE/);
    await expect(page).toHaveURL(/opponent=Lux/);
    await expect(page.locator('article.match-card')).toHaveCount(4);
});

test('administrator registers a future season without starting it', async ({ page }, testInfo) => {
    await login(page);
    await openMenu(page, testInfo.project.name);
    await page.getByRole('link', { name: 'Temporadas', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Temporadas', exact: true })).toBeVisible();
    await expect(page.getByText('Atual', { exact: true })).toBeVisible();
    const name = '2027-' + testInfo.project.name;
    await page.getByLabel('Nome da temporada').fill(name);
    await page.getByRole('button', { name: 'Cadastrar temporada', exact: true }).click();
    await expect(page.getByRole('button', { name: 'Iniciar ' + name, exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await page.screenshot({ path: 'storage/framework/testing/screenshots/seasons-'+testInfo.project.name+'.png', fullPage: true });
    await page.goto('/dashboard');
    await expect(page.locator('select[name="season"] option')).toHaveCount(1);
});


test('player search, encounter notes, favorite filters and goals work together', async ({ page }, testInfo) => {
    await login(page);
    await page.locator('#global-player-search').fill('Jogador 6');
    await page.getByRole('button', { name: 'Buscar jogador', exact: true }).click();
    await page.getByRole('link', { name: /Jogador 6#BR1/ }).click();
    await expect(page.getByRole('heading', { name: 'Jogador 6#BR1' })).toBeVisible();
    await page.locator('#player-note').fill('Revisar os encontros - '+testInfo.project.name);
    await page.getByRole('button', { name: 'Salvar anotação do jogador' }).click();
    await expect(page.getByText('Anotação do jogador salva.', { exact: true })).toBeVisible();
    const note = page.locator('textarea[id^="match-note-"]').first();
    if (!(await note.isVisible())) await page.getByText('Anotar esta partida', { exact: true }).first().click();
    await note.fill('Rever objetivo final');
    await page.getByRole('button', { name: 'Salvar anotação da partida' }).first().click();
    await expect(page.getByText('Anotação da partida salva.', { exact: true })).toBeVisible();
    await page.goto('/jogadores?q=Jogador%206');
    await expect(page.getByText(/Com anotação: Revisar os encontros/)).toBeVisible();
    await page.goto('/dashboard?tab=history&role=MIDDLE');
    await expect(page.getByText('Rever objetivo final', { exact: true })).toBeVisible();
    await page.getByLabel('Nome do filtro favorito').fill('Mid favorito '+testInfo.project.name);
    await page.getByRole('button', { name: 'Salvar filtro favorito', exact: true }).click();
    await expect(page.getByRole('link', { name: 'Mid favorito '+testInfo.project.name, exact: true })).toBeVisible();
    await page.getByLabel('Título', { exact: true }).fill('Meta do navegador '+testInfo.project.name);
    await page.getByLabel('Alvo', { exact: true }).fill('10');
    await page.getByRole('button', { name: 'Criar meta', exact: true }).click();
    await expect(page.getByRole('heading', { name: 'Meta do navegador '+testInfo.project.name, exact: true })).toBeVisible();
    expect(await page.evaluate(() => document.documentElement.scrollWidth > window.innerWidth)).toBe(false);
    await page.screenshot({ path: 'storage/framework/testing/screenshots/workspace-'+testInfo.project.name+'.png', fullPage: true });
});
