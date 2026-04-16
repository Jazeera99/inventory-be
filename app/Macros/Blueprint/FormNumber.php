<?php

namespace App\Macros\Blueprint;

use Illuminate\Database\Schema\Blueprint;

Blueprint::macro('formNumber', function () {
    return $this->string('form_number', 20)->unique();
});
