import { test } from 'node:test';
import assert from 'node:assert/strict';
import { createSyncProgress } from '../../resources/js/sync-progress.js';

test('progress finishes without reloading or rescheduling', async () => {
    globalThis.document = { hidden: false };
    globalThis.fetch = async () => ({ ok: true, json: async () => ({ run: { active: false, status: 'completed', processed: 5 } }) });
    const progress = createSyncProgress('/status', { active: true });
    let schedules = 0;
    progress.schedule = () => schedules++;
    await progress.poll();
    assert.equal(progress.finishedNow, true);
    assert.equal(progress.run.processed, 5);
    assert.equal(schedules, 0);
});

test('failed polling is bounded and gives a recoverable message', async () => {
    globalThis.document = { hidden: false };
    globalThis.fetch = async () => { throw new Error('network'); };
    const progress = createSyncProgress('/status', { active: true });
    let schedules = 0;
    progress.schedule = () => schedules++;
    for (let i = 0; i < 5; i++) await progress.poll();
    assert.equal(progress.failures, 5);
    assert.equal(schedules, 4);
    assert.ok(progress.error.length > 0);
});

test('destroy prevents a pending poll from scheduling another timer', async () => {
    globalThis.document = { hidden: false };
    let resolve;
    globalThis.fetch = () => new Promise(r => { resolve = r; });
    const progress = createSyncProgress('/status', { active: true });
    const polling = progress.poll();
    progress.destroy();
    resolve({ ok: true, json: async () => ({ run: { active: true } }) });
    await polling;
    assert.equal(progress.disposed, true);
    assert.equal(progress.timer, null);
});
