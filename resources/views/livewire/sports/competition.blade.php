<div class="nt-sports nt-view-in">
    <x-ui.page-head :title="$competition->name" crumb="Football" :description="$competition->description ?: 'Every match open for betting in '.$competition->name.'.'" />
    <livewire:sports.fixture-list :competition-id="$competition->id" :key="'competition-'.$competition->id" heading="Matches" />
</div>
