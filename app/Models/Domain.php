<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class Domain extends Model
{
    protected $fillable =[
        'domain',
        'tenant_id'
    ];

public function tenant()
{
    return $this->belongsTo(Tenant::class, 'tenant_id', 'id');
}
}
