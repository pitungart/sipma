{{-- Ringkasan terbit massal KTM: berapa yang terbit dan siapa yang dilewati beserta alasannya --}}
<div class="sipma-batch-plan">
    @if ($plan['ready'] > 0)
        <x-sipma.notice tone="success" icon="lucide-circle-check">
            {{ trans_choice('admin.student_card.batch.ready', $plan['ready'], ['count' => $plan['ready']]) }}
            @if ($plan['withoutAccount'] > 0)
                {{ trans_choice('admin.student_card.batch.without_account', $plan['withoutAccount'], ['count' => $plan['withoutAccount']]) }}
            @endif
        </x-sipma.notice>
    @else
        <x-sipma.notice tone="danger" icon="lucide-circle-x">{{ __('admin.student_card.batch.nothing_ready') }}</x-sipma.notice>
    @endif

    @if ($plan['skipped'] !== [])
        <h4 class="sipma-batch-plan-title">
            {{ trans_choice('admin.student_card.batch.skipped', count($plan['skipped']), ['count' => count($plan['skipped'])]) }}
        </h4>
        <ul class="sipma-batch-plan-list">
            @foreach ($plan['skipped'] as $skipped)
                <li>
                    <strong>{{ $skipped['name'] }}</strong>
                    <span>{{ $skipped['reason'] }}</span>
                </li>
            @endforeach
        </ul>
    @endif
</div>
