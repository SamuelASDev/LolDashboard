<nav class="account-tabs mb-5" aria-label="{{ $aggregate ? 'Estatísticas de todas as contas' : 'Estatísticas da conta' }}">
    @foreach(['overview'=>'Visão geral','history'=>'Partidas','champions'=>'Campeões','matchups'=>'Confrontos','patterns'=>'Padrões','records'=>'Recordes','progress'=>'Evolução'] as $key=>$label)
        <a href="{{ route($analyticsRoute, array_merge(request()->except('page'), $analyticsParams, ['tab'=>$key, 'season'=>$season->id])) }}" class="account-tab" @if($tab === $key) aria-current="page" @endif>{{ $label }}</a>
    @endforeach
</nav>
