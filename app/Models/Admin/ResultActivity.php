<?php

namespace App\Models\Admin;

use Illuminate\Database\Eloquent\Model;

class ResultActivity extends Model
{
    protected $fillable = ['result_id','activity_id','marks'];

    public function activity() {
        return $this->belongsTo(ExtraCurricularActivity::class);
    }
}
