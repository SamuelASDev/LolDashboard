<section class="space-y-6">
    <header>
        <h2 class="text-lg font-medium text-slate-100">
            {{ __('Excluir meu acesso') }}
        </h2>

        <p class="mt-1 text-sm text-slate-400">
            {{ __('A exclusão remove definitivamente seu acesso, contas e partidas. Guarde as informações que deseja manter antes de continuar.') }}
        </p>
    </header>

    <x-danger-button
        x-data=""
        x-on:click.prevent="$dispatch('open-modal', 'confirm-user-deletion')"
    >{{ __('Excluir meu acesso') }}</x-danger-button>

    <x-modal name="confirm-user-deletion" :show="$errors->userDeletion->isNotEmpty()" focusable>
        <form method="post" action="{{ route('profile.destroy') }}" class="p-6">
            @csrf
            @method('delete')

            <h2 class="text-lg font-medium text-slate-100">
                {{ __('Excluir definitivamente seu acesso?') }}
            </h2>

            <p class="mt-1 text-sm text-slate-400">
                {{ __('Confirme com sua senha. Todas as contas e partidas vinculadas serão removidas definitivamente.') }}
            </p>

            <div class="mt-6">
                <x-input-label for="password" value="{{ __('Senha') }}" class="sr-only" />

                <x-text-input
                    id="password"
                    name="password"
                    type="password"
                    class="mt-1 block w-3/4"
                    placeholder="{{ __('Senha') }}"
                />

                <x-input-error :messages="$errors->userDeletion->get('password')" class="mt-2" />
            </div>

            <div class="mt-6 flex justify-end">
                <x-secondary-button x-on:click="$dispatch('close')">
                    {{ __('Cancelar') }}
                </x-secondary-button>

                <x-danger-button class="ms-3">
                    {{ __('Excluir meu acesso') }}
                </x-danger-button>
            </div>
        </form>
    </x-modal>
</section>
