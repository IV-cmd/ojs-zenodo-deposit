<?php

declare(strict_types=1);

namespace APP\plugins\generic\zenodoDeposit\classes\ojs;

use APP\plugins\generic\zenodoDeposit\classes\DepositState;
use APP\plugins\generic\zenodoDeposit\classes\DepositStateStore;
use Illuminate\Support\Facades\DB;

final class DatabaseDepositStateStore implements DepositStateStore
{
    private const TABLE = 'zenodo_deposits';

    public function get(int $publicationId): ?DepositState
    {
        $row = DB::table(self::TABLE)->where('publication_id', $publicationId)->first();

        if ($row === null) {
            return null;
        }

        return new DepositState(
            (int) $row->publication_id,
            (int) $row->submission_id,
            (int) $row->context_id,
            (string) $row->status,
            $row->deposition_id === null ? null : (int) $row->deposition_id,
            $row->dataset_deposition_id === null ? null : (int) $row->dataset_deposition_id,
            $row->doi === null ? null : (string) $row->doi,
            $row->dataset_doi === null ? null : (string) $row->dataset_doi,
            (int) $row->attempts,
            $row->last_error === null ? null : substr((string) $row->last_error, 0, 2000)
        );
    }

    public function save(DepositState $state): void
    {
        $now = date('Y-m-d H:i:s');
        $values = $this->values($state);
        $values['updated_at'] = $now;
        $query = DB::table(self::TABLE)->where('publication_id', $state->publicationId);

        if ($query->update($values) === 0) {
            $values['created_at'] = $now;
            DB::table(self::TABLE)->insertOrIgnore($values);
        }

        $this->mirrorToPublication($state);
    }

    /**
     * Project the state onto the publication's `zenodoDeposit` setting (M4)
     * so it reaches the publication object and API responses. The
     * zenodo_deposits table stays the source of truth; a mirror failure is
     * logged, never thrown — deposit correctness must not depend on the UI.
     * Repo::publication()->edit() is not used: it requires a request user,
     * which does not exist in the queue worker.
     */
    private function mirrorToPublication(DepositState $state): void
    {
        try {
            $key = [
                'publication_id' => $state->publicationId,
                'locale' => '',
                'setting_name' => 'zenodoDeposit',
            ];
            $value = json_encode([
                'status' => $state->status,
                'depositionId' => $state->depositionId,
                'datasetDepositionId' => $state->datasetDepositionId,
                'doi' => $state->doi,
                'datasetDoi' => $state->datasetDoi,
                'attempts' => $state->attempts,
                'lastError' => $state->lastError,
            ]);

            $query = DB::table('publication_settings')
                ->where('publication_id', $key['publication_id'])
                ->where('setting_name', $key['setting_name'])
                ->where('locale', '');

            if ($query->update(['setting_value' => $value]) === 0) {
                DB::table('publication_settings')
                    ->insertOrIgnore($key + ['setting_value' => $value]);
            }
        } catch (\Throwable $exception) {
            error_log(
                '[zenodoDeposit] publication_id=' . $state->publicationId
                    . ' publication mirror failed (' . get_class($exception) . ')'
            );
        }
    }

    public function ensurePending(DepositState $state): void
    {
        $now = date('Y-m-d H:i:s');
        $values = $this->values($state);
        $values['created_at'] = $now;
        $values['updated_at'] = $now;
        DB::table(self::TABLE)->insertOrIgnore($values);
    }

    /**
     * @return array<string, int|string|null>
     */
    private function values(DepositState $state): array
    {
        return [
            'publication_id' => $state->publicationId,
            'submission_id' => $state->submissionId,
            'context_id' => $state->contextId,
            'status' => $state->status,
            'deposition_id' => $state->depositionId,
            'dataset_deposition_id' => $state->datasetDepositionId,
            'doi' => $state->doi,
            'dataset_doi' => $state->datasetDoi,
            'attempts' => $state->attempts,
            'last_error' => $state->lastError === null ? null : substr($state->lastError, 0, 2000),
        ];
    }
}
