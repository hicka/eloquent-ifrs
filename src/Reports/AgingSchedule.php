<?php

/**
 * Eloquent IFRS Accounting
 *
 * @author    Edward Mungai
 * @copyright Edward Mungai, 2020, Germany
 * @license   MIT
 */

namespace IFRS\Reports;

use Carbon\Carbon;

use Illuminate\Support\Facades\Auth;

use IFRS\Models\Entity;
use IFRS\Models\Account;
use IFRS\Models\Currency;

class AgingSchedule
{
    /**
     * Aging Schedule Currency.
     *
     * @var Currency
     */
    protected $currency;

    /**
     * Aging Schedule Entity.
     *
     * @var Entity
     */
    protected $entity;

    /**
     * Aging Schedule accounts.
     *
     * @var array
     */
    public $accounts = [];

    /**
     * Aging Schedule balances.
     *
     * @var array
     */
    public $balances = [];


    /**
     * Aging Schedule brackets.
     *
     * @var array
     */
    public $brackets = [];

    /**
     * Get Aging Schedule bracket the Transaction belongs to.
     *
     * @param int   $age
     * @param array $brackets
     *
     * @return string
     */
    private function getBracket($age, $brackets): string
    {
        if (count($brackets) == 1) {
            return array_key_first($brackets);
        } else {
            $bracket_name = array_key_first($brackets);
            $bracket = array_shift($brackets);
            if ($age < $bracket) {
                return $bracket_name;
            } else {
                return $this->getBracket($age, $brackets);
            }
        }
    }
    /**
     * Agine Schedule for the account type as at the endDate.
     * @param int $entity_id
     * @param string $accountType
     * @param int    $currencyId
     * @param string $endDate
     */
    public function __construct($entity_id,string $accountType = Account::RECEIVABLE, string $endDate = null, int $currencyId = null)
    {
        $this->period['endDate'] = is_null($endDate) ? Carbon::now() : Carbon::parse($endDate);
        $this->entity = Entity::where('id','=',$entity_id)->first();
        $this->currency = is_null($currencyId) ? $this->entity->currency : Currency::find($currencyId);

        $this->brackets = config('ifrs')['aging_schedule_brackets'];

        $balances = array_fill_keys(array_keys($this->brackets), 0);

        foreach (Account::where("account_type", $accountType)->where('entity_id','=',$entity_id)->get() as $account) {
            $account_balances = array_fill_keys(array_keys($this->brackets), 0);

            $schedule = new AccountSchedule($entity_id,$account->id, $currencyId, $endDate);
            $schedule->getTransactions();

            foreach ($schedule->transactions as $transaction) {
                if ($transaction->unclearedAmount > 0) {
                    $bound = $this->getBracket($transaction->age, $this->brackets);

                    $balances[$bound] += $transaction->unclearedAmount;
                    $account_balances[$bound] += $transaction->unclearedAmount;
                }
            }
            if (array_sum($account_balances) > 0) {
                $account->balances = $account_balances;
                array_push($this->accounts, $account);
            }
        }

        $this->balances = $balances;
    }

    /**
     * Print Aging Schedule attributes.
     *
     * @return object
     */
    public function attributes()
    {
        return (object) [
            "Currency" => $this->currency->name,
            "Entity" => $this->entity->name,
            "Accounts" => $this->accounts,
            "Balances" => $this->balances,
            "Brackets" => $this->brackets
        ];
    }
}
