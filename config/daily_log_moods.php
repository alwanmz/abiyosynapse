<?php

/**
 * Catalog of moods available in the Daily Logs form.
 *
 * Kept as a config so we can localize, reorder, or swap emoji without
 * touching the model/controller. The frontend reads the same list via
 * the controller props so labels never drift between BE and FE.
 *
 * Each entry: slug => [emoji, label]
 */
return [
    'produktif'   => ['emoji' => '🚀', 'label' => 'Produktif'],
    'fokus'       => ['emoji' => '🎯', 'label' => 'Fokus'],
    'semangat'    => ['emoji' => '🔥', 'label' => 'Semangat'],
    'happy'       => ['emoji' => '😄', 'label' => 'Happy'],
    'tenang'      => ['emoji' => '😌', 'label' => 'Tenang'],
    'kreatif'     => ['emoji' => '💡', 'label' => 'Kreatif'],
    'kolaboratif' => ['emoji' => '🤝', 'label' => 'Kolaboratif'],
    'belajar'     => ['emoji' => '📚', 'label' => 'Belajar'],
    'biasa'       => ['emoji' => '😐', 'label' => 'Biasa Aja'],
    'lelah'       => ['emoji' => '😩', 'label' => 'Lelah'],
    'overwhelmed' => ['emoji' => '😵', 'label' => 'Overwhelmed'],
    'stuck'       => ['emoji' => '🧱', 'label' => 'Stuck'],
    'kesel'       => ['emoji' => '😤', 'label' => 'Kesel'],
    'sedih'       => ['emoji' => '😔', 'label' => 'Sedih'],
    'kurang_fit'  => ['emoji' => '🤒', 'label' => 'Kurang Fit'],
    'caffeinated' => ['emoji' => '☕',  'label' => 'Caffeinated'],
];
