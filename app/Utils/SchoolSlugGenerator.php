<?php

namespace App\Utils;

use App\Models\Tenant;

class SchoolSlugGenerator
{
    public function generate(Tenant $tenant): string
    {
        // Use tenant attributes to generate DB name
        $name = strtolower(str_replace([' ', '.', '-'], '_', $tenant->name));
        // $domain = strtolower(str_replace('.', '_', $tenant->domain ?? 'domain'));

        // Example: first 2 letters of name + domain + _db
        return $name   . '_db';
    }
}
