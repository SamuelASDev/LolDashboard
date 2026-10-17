<x-guest-layout>
    <x-auth-session-status class="mb-4" :status="session('status')" />

    <form method="POST" action="{{ route('login') }}">
        @csrf

        <div>
            <x-input-label for="email" :value="__('E-mail')" />
            <x-text-input id="email" class="block mt-1 w-full" type="email" name="email" :value="old('email')" required autofocus autocomplete="username" />
            <x-input-error :messages="$errors->get('email')" class="mt-2" />
        </div>

        <div class="mt-4">
            <x-input-label for="password" :value="__('Senha')" />

            <x-text-input id="password" class="block mt-1 w-full"
                            type="password"
                            name="password"
                            required autocomplete="current-password" />

            <x-input-error :messages="$errors->get('password')" class="mt-2" />
        </div>

        <div class="block mt-4">
            <label for="remember_me" class="inline-flex items-center">
                <input id="remember_me" type="checkbox" class="rounded border-gray-300 text-indigo-600 shadow-sm focus:ring-indigo-500" name="remember">
                <span class="ms-2 text-sm text-slate-400">{{ __('Manter conectado') }}</span>
            </label>
        </div>

        <div class="flex items-center justify-between mt-4">
            {{-- Link para a tela de registro (Register) ? esquerda --}}
            @if (Route::has('register'))
                <a class="underline text-sm text-slate-400 hover:text-slate-200 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500" href="{{ route('register') }}">
                    {{ __('Não tem uma conta? Cadastre-se') }}
                </a>
            @endif

            {{-- Links de recuperação e botão de Login ? direita --}}
            <div class="flex items-center">
                @if (Route::has('password.request'))
                    <a class="underline text-sm text-slate-400 hover:text-slate-400 rounded-md focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-indigo-500 me-3" href="{{ route('password.request') }}">
                        {{ __('Esqueceu a senha?') }}
                    </a>
                @endif

                <x-primary-button>
                    {{ __('Entrar') }}
                </x-primary-button>
            </div>
        </div>
    </form>
</x-guest-layout>