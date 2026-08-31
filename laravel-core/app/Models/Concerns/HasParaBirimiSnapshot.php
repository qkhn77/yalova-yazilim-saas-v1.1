<?php

namespace App\Models\Concerns;

use App\Muhasebe\Servisler\ParaBirimiDonusumServisi;

/** Yeni çoklu para birimi kayıtlarında işlem/baz tutar snapshotı üretir. */
trait HasParaBirimiSnapshot
{
    public static function bootHasParaBirimiSnapshot(): void
    {
        static::creating(function (self $model): void {
            if ($model->getAttribute('baz_tutar') !== null) {
                return;
            }

            $firmaId = (int) $model->getAttribute('firma_id');
            $tutar = $model->getAttribute($model->paraBirimiSnapshotTutarAlani());
            $paraBirimi = strtoupper(trim((string) ($model->getAttribute('para_birimi') ?: 'TRY')));

            if ($firmaId <= 0 || $tutar === null || $tutar === '') {
                return;
            }

            $snapshot = app(ParaBirimiDonusumServisi::class)->tutariBazParaBirimineHazirla(
                $firmaId,
                (string) $tutar,
                $paraBirimi,
                $model->getAttribute('para_birimi_snapshot_tarihi')
                    ?: $model->getAttribute($model->paraBirimiSnapshotTarihAlani()),
            );

            $model->setAttribute('kur', $snapshot['kur']);
            $model->setAttribute('baz_para_birimi', $snapshot['baz_para_birimi']);
            $model->setAttribute('baz_tutar', $snapshot['baz_tutar']);
            unset($model->attributes['para_birimi_snapshot_tarihi']);
        });
    }

    protected function paraBirimiSnapshotTutarAlani(): string
    {
        return 'tutar';
    }

    protected function paraBirimiSnapshotTarihAlani(): string
    {
        return 'created_at';
    }
}
