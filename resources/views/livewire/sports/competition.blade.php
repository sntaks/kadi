<div class="nt-sports nt-view-in">
    <x-ui.page-head :title="$competition->name" :crumb="$sportName" :description="$competition->description ?: 'Every match open for betting in '.$competition->name.'.'" />
    <div class="nt-sport-wrap"><div class="nt-sport-layout">
        <x-sports.sport-nav :sport="$sport" :$sports :$competitions :active="$competition->slug" />
        <div class="nt-sport-main">
            <livewire:sports.fixture-list :competition-id="$competition->id" :sport="$sport" :key="'competition-'.$competition->id" heading="Matches" :icon="$icon" />
        </div>
    </div></div>
</div>
