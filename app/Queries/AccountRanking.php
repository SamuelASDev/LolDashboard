<?php

namespace App\Queries;

use Illuminate\Database\Eloquent\Relations\HasMany;

class AccountRanking
{
    public function ordered(HasMany $query): HasMany
    {
        return $query->orderByRaw("CASE
            WHEN elo LIKE 'CHALLENGER%' THEN 9 WHEN elo LIKE 'GRANDMASTER%' THEN 8
            WHEN elo LIKE 'MASTER%' THEN 7 WHEN elo LIKE 'DIAMOND%' THEN 6
            WHEN elo LIKE 'EMERALD%' THEN 5 WHEN elo LIKE 'PLATINUM%' THEN 4
            WHEN elo LIKE 'GOLD%' THEN 3 WHEN elo LIKE 'SILVER%' THEN 2
            WHEN elo LIKE 'BRONZE%' THEN 1 WHEN elo LIKE 'IRON%' THEN 0 ELSE -1 END DESC")
            ->orderByRaw("CASE WHEN elo LIKE '% I' THEN 1 WHEN elo LIKE '% II' THEN 2
                WHEN elo LIKE '% III' THEN 3 WHEN elo LIKE '% IV' THEN 4 ELSE 5 END ASC")
            ->orderByDesc('pdl')->orderBy('id');
    }
}
