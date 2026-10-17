export const createSyncProgress = (endpoint, initial) => ({
    run: initial,
    error: '',
    finishedNow: false,
    timer: null,
    controller: null,
    failures: 0,
    disposed: false,
    get label() {
        return {queued: 'Na fila', running: 'Em andamento', retrying: 'Aguardando nova tentativa',
            completed: 'Concluída', failed: 'Interrompida', partial: 'Parcial', cancelled: 'Cancelada'}[this.run.status] ?? this.run.status;
    },
    init() {
        if (this.run.active) this.schedule();
    },
    schedule() {
        if (this.disposed) return;
        this.timer = setTimeout(() => this.poll(), 5000);
    },
    async poll() {
        if (this.disposed) return;
        if (document.hidden) { this.schedule(); return; }
        this.controller = new AbortController();
        const timeout = setTimeout(() => this.controller?.abort(), 10000);
        try {
            const response = await fetch(endpoint, {headers: {'Accept': 'application/json'}, signal: this.controller.signal});
            if (!response.ok) throw new Error('status');
            const {run} = await response.json();
            if (!run) throw new Error('missing');
            this.finishedNow = this.run.active && !run.active;
            this.run = run;
            this.failures = 0;
            this.error = '';
        } catch {
            this.failures++;
            this.error = 'Não foi possível consultar o progresso. A tarefa pode continuar no servidor.';
        } finally {
            clearTimeout(timeout);
            this.controller = null;
            if (this.run.active && this.failures < 5) this.schedule();
        }
    },
    destroy() {
        this.disposed = true;
        clearTimeout(this.timer);
        this.controller?.abort();
    }
});

