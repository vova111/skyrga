<?php

namespace App;

use Carbon\Carbon;
use Illuminate\Database\Eloquent\Model;

class Target extends Model
{
    const PATH_TO_UBOT_ARCHIVE = '/files/ubot/';
    const UBOT_ARCHIVE_FILENAME = 'ubot.rar';

    public function profile()
    {
        return $this->belongsTo(Profile::class);
    }

    public function project()
    {
        return $this->belongsTo(Project::class);
    }

    public function account()
    {
        return $this->belongsTo(Account::class);
    }

    public function getEmail()
    {
        if (!is_null($this->account)) {
            return $this->account->email->email;
        } elseif (!is_null($this->profile->reserveEmail)) {
            return $this->profile->reserveEmail->email;
        }

        return $this->profile->email->email;
    }

    public function getEmailID()
    {
        if (!is_null($this->account)) {
            return $this->account->email->id;
        } elseif (!is_null($this->profile->reserveEmail)) {
            return $this->profile->reserveEmail->id;
        }

        return $this->profile->email->id;
    }

    public function getAccount()
    {
        if (!$this->account_id) {
            if (!$this->project->account_id) {
                $account = new Account();
                $account->generateRandomAccount($this);
                $account->save();

                $this->account_id = $account->id;
                $this->save();

                return $account;
            } else {
                $account = Account::find($this->project->account_id);

                $this->account_id = $account->id;
                $this->save();

                return $account;
            }
        }

        return $this->account;
    }

    public function getDomainForUbot()
    {
        return str_replace('.', '-', $this->project->domain->domain);
    }

    public function getProfileUbotPath()
    {
        $parts = parse_url($this->profile->domain);
        return substr($parts['host'], 0, strpos($parts['host'], '.'));
    }

    /*public static function getTargetsCounts($targets)
    {
        $counts = [];

        foreach ($targets as $key => $collection) {
            $counts[$key] = $collection->filter(function ($target) {
                if ($target->is_register == 1) {
                    return true;
                }
            })->count();
        }

        return $counts;
    }

    public static function getNextTarget($targets)
    {
        $counts = Target::getTargetsCounts($targets);
        $index = false;
        $previous = 100;

        foreach ($counts as $project_id => $count) {
            if ($count != $targets[$project_id]->count()) {
                if ($count < $previous) {
                    $previous = $count;
                    $index = $project_id;
                }
            }
        }

        if ($index != false) {
            $first = $targets[$index]->first(function ($target) {
                return $target->is_register == 0;
            });

            return $first;
        }

        return null;
    }*/

    public static function getMatrix($startDate)
    {
        $date = Carbon::createFromFormat('Y-m-d', $startDate);
        $profiles = Profile::select('id')->orderBy('id')->get();
        $profilesCount = $profiles->count();

        $tagrets = self::select('register_date', 'profile_id')->where('register_date', '>=', $startDate)
            ->orderBy('register_date', 'asc')
            ->orderBy('profile_id')
            ->get()
            ->groupBy('register_date')
            ->map(function ($item) {
                return $item->pluck('profile_id');
            })
            ->toArray();

        $matrix = [];

        for ($i = 0; $i < 730; $i++) {
            $dateStr = $date->format('Y-m-d');
            $profs = [];

            for ($j = 0; $j < $profilesCount; $j++) {
                if (isset($tagrets[$dateStr]) && in_array($profiles[$j]->id, $tagrets[$dateStr])) {
                    $profs[] = $profiles[$j]->id;
                } else {
                    $profs[] = 0;
                }
            }

            $matrix[$dateStr] = $profs;
            $date->addDay(1);
        }

        return $matrix;
    }
}
