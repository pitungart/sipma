<x-filament-widgets::widget>
    <div class="sipma-card sipma-fill sipma-clip">
        <div class="sipma-card-head sipma-card-head-ruled">
            <h2 class="sipma-card-title">{{ __('admin.dashboard.latest_applicants') }}</h2>
            <a href="{{ \App\Filament\Admin\Resources\StudentResource::getUrl() }}" class="sipma-link">{{ __('admin.dashboard.see_all') }}</a>
        </div>

        @if ($rows->isEmpty())
            <x-sipma.empty icon="lucide-user-plus" :title="__('admin.dashboard.latest_applicants_empty')" />
        @else
            <div class="sipma-table-scroll">
                <table class="sipma-table">
                    <thead>
                        <tr>
                            <th>{{ __('admin.dashboard.applicant_name') }}</th>
                            <th>{{ __('admin.program.label') }}</th>
                            <th>{{ __('admin.dashboard.status') }}</th>
                            <th class="sipma-end">{{ __('admin.dashboard.date') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($rows as $row)
                            <tr>
                                <td>
                                    <div class="sipma-person">
                                        <span class="sipma-avatar sipma-tone-{{ $row['tone'] }}" aria-hidden="true">{{ $row['initials'] }}</span>
                                        <div>
                                            <a href="{{ $row['url'] }}" class="sipma-person-name sipma-row-link">{{ $row['name'] }}</a>
                                            <div class="sipma-person-meta">{{ $row['country'] }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td>{{ $row['program'] }}</td>
                                <td><x-sipma.status-badge :status="$row['status']" /></td>
                                <td class="sipma-end sipma-muted sipma-nowrap">{{ $row['date'] }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-filament-widgets::widget>
