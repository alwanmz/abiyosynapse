<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Project extends Model
{
    use SoftDeletes;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'team_id',
        'client_id',
        'kode_project',
        'no_kontrak',
        'tgl_mulai_kontrak',
        'tgl_selesai_kontrak',
        'tgl_implementasi',
        'tgl_selesai_implementasi',
        'jenis_pekerjaan',
        'marketing_internal',
        'project_manager_id',
        'status',
        'start_date',
        'end_date',
        'created_by',
        'file_path',
        'file_name',
        'image_path',
        'image_name',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'tgl_mulai_kontrak' => 'date',
        'tgl_selesai_kontrak' => 'date',
        'tgl_implementasi' => 'date',
        'tgl_selesai_implementasi' => 'date',
    ];

    public function team(): BelongsTo
    {
        return $this->belongsTo(Team::class);
    }

    public function client(): BelongsTo
    {
        return $this->belongsTo(Client::class);
    }

    public function projectManager(): BelongsTo
    {
        return $this->belongsTo(User::class, 'project_manager_id');
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function timelines(): HasMany
    {
        return $this->hasMany(ProjectTimeline::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }

}
