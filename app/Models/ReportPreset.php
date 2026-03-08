<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Modello: ReportPreset
 * - Preset salvato dall'admin per eseguire report economici preconfigurati.
 *
 * Scelte progettuali:
 * - report_type identifica il "motore" del report (fonte dati + logica timestamp).
 * - metrics, dimensions e filters sono salvati come JSON e castati ad array.
 * - chart_type è solo una preferenza di visualizzazione UI.
 */
class ReportPreset extends Model
{
    use SoftDeletes;

    /**
     * Tabella associata al model.
     *
     * La proprietà è facoltativa perché Laravel la dedurrebbe comunque,
     * ma la esplicitiamo per chiarezza e per coerenza con il progetto.
     *
     * @var string
     */
    protected $table = 'report_presets';

    /**
     * Attributi assegnabili massivamente.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'description',
        'report_type',
        'metrics',
        'dimensions',
        'filters',
        'chart_type',
        'created_by',
    ];

    /**
     * Cast automatici degli attributi.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'metrics'    => 'array',
            'dimensions' => 'array',
            'filters'    => 'array',
        ];
    }

    /**
     * Utente che ha creato il preset.
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * Scope: limita i preset creati da uno specifico utente.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeForCreator($query, int $userId)
    {
        return $query->where('created_by', $userId);
    }

    /**
     * Scope: limita i preset di uno specifico tipo report.
     *
     * @param \Illuminate\Database\Eloquent\Builder $query
     */
    public function scopeOfType($query, string $reportType)
    {
        return $query->where('report_type', $reportType);
    }
}