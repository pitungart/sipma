<?php

namespace App\Filament\Admin\Resources\CourseResource\Pages;

use App\Filament\Admin\Resources\CourseResource;
use App\Filament\Admin\Resources\Pages\ManageMasterData;

class ManageCourses extends ManageMasterData
{
    protected static string $resource = CourseResource::class;

    /**
     * @return array<int|string, string>
     */
    public function getBreadcrumbs(): array
    {
        return [__('admin.groups.academic'), CourseResource::getPluralModelLabel()];
    }
}
