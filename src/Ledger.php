<?php
/**
 * Created by PhpStorm.
 * User: andre
 * Date: 2017-06-24
 * Time: 1:33 PM
 */

namespace FannyPack\Ledger;


use FannyPack\Ledger\Exceptions\InvalidRecipientException;
use FannyPack\Ledger\Exceptions\InsufficientBalanceException;
use Illuminate\Routing\Router;

class Ledger
{
    /**
     * @var Router
     */
    protected $router;

    /**
     * Ledger constructor.
     * @param Router $router
     */
    public function __construct(Router $router)
    {
        $this->router = $router;
    }

    /**
     * debit a ledgerable instance
     *
     * @param $to
     * @param string $from
     * @param $amount
     * @param $reason
     * @return mixed
     */
    public function debit($to, $from, $amount, $reason)
    {
        $balance = $to->balance();
        
        $data = [
            'money_from' => $from,
            'debit' => 1, 
            'reason' => $reason, 
            'amount' => $amount, 
            'current_balance' => $balance + $amount,
        ];

        return $this->log($to, $data);
    }

    /**
     * credit a ledgerable instance
     *
     * @param $from
     * @param string $to
     * @param $amount
     * @param $reason
     * @return mixed
     * @throws InsufficientBalanceException
     */
    public function credit($from, $to, $amount, $reason)
    {
        $balance = $from->balance();

        if ($balance == 0 || $amount > $balance )
            throw new InsufficientBalanceException("Insufficient balance");
        
        $data = [
            'money_to' => $to,
            'credit' => 1, 
            'reason' => $reason, 
            'amount' => $amount,
            'current_balance' => $balance - $amount,
        ];

        return $this->log($from, $data);
    }

    /**
     * persist an entry to the ledger
     * 
     * @param $ledgerable
     * @param array $data
     * @return mixed
     */
    protected function log($ledgerable, array $data)
    {
        return $ledgerable->entries()->create($data);
    }

    /**
     * balance of a ledgerable instance
     * 
     * @param $ledgerable
     * @return float
     */
    public function balance($ledgerable)
    {
        $credits = $ledgerable->credits()->sum('amount');
        $debits = $ledgerable->debits()->sum('amount');
        $balance = $debits - $credits;
        return $balance;
    }

    /**
     * transfer an amount to each ledgerable instance
     * 
     * @param $from
     * @param $to
     * @param $amount
     * @param string $reason
     * @return mixed
     * @throws InvalidRecipientException
     * @throws InsufficientBalanceException
     */
    public function transfer($from, $to, $amount, $reason = "funds transfer")
    {
        if (!is_array($to))
            return $this->transferOnce($from, $to, $amount, $reason);

        $total_amount = $amount * count($to);
        if ($total_amount > $from->balance())
            throw new InsufficientBalanceException("Insufficient balance");
        
        $recipients = [];
        foreach ($to as $recipient)
        {
            array_push($recipients, $this->transferOnce($from, $recipient, $amount, $reason));
        }
        
        return $recipients;
    }

    /**
     * transfer an amount to one ledgerable instance
     *
     * @param $from
     * @param $to
     * @param $amount
     * @param $reason
     * @return mixed
     * @throws InsufficientBalanceException
     * @throws InvalidRecipientException
     */
    protected function transferOnce($from, $to, $amount, $reason)
    {
        if (get_class($from) == get_class($to) && $from->id == $to->id)
            throw new InvalidRecipientException("Source and recipient cannot be the same object");

        if (get_class($to) === "App\Escrow") {
            $this->credit($from, "Escrow", $amount, $reason);
            return $this->debit("Escrow", $from, $amount, $reason);
        }
        if (get_class($from) === "App\Escrow") {
            $this->credit("Escrow", $to, $amount, $reason);
            return $this->debit($to, "Escrow", $amount, $reason);
        } 
        $this->credit($from, $to->name, $amount ,$reason);
        return $this->debit($to, $from->name, $amount, $reason);
    }

    /**
     * register routes for ledger api access
     */
    public function routes()
    {
        $this->router->group(['namespace' => 'FannyPack\Ledger\Http\Controllers', 'prefix' => 'entries'], function() {
            $this->router->get('ledger', 'LedgerController@index');
            $this->router->get('ledger/{entry_id}', 'LedgerController@show');
        });
    }
}