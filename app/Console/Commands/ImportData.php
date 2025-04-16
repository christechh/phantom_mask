<?php
namespace App\Console\Commands;

use App\Models\Mask;
use App\Models\Member;
use App\Models\Pharmacy;
use App\Models\PurchaseHistory;
use App\Services\OpeningHoursParser;
use Carbon\Carbon;
use Illuminate\Console\Command;

class ImportData extends Command
{
    protected $signature   = 'data:import';
    protected $description = 'Import raw data into the database';
    protected $openingHoursParser;

    public function __construct()
    {
        parent::__construct();
        $this->openingHoursParser = new OpeningHoursParser();
    }

    public function handle()
    {
        $this->importPharmacies();
        $this->importUsers();
        $this->info('Data import completed successfully.');
    }

    private function importPharmacies()
    {
        $filePath = storage_path('app/data/pharmacies.json');
        $this->info("File path: $filePath");

        if (! file_exists($filePath)) {
            $this->error("File does not exist at path: $filePath");
            return;
        }

        $fileContent = file_get_contents($filePath);
        $this->info("File content:\n" . $fileContent);

        // parse JSON
        $pharmacyData = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error decoding JSON: ' . json_last_error_msg());
            return;
        }

        $this->info("Parsed data:\n" . print_r($pharmacyData, true));

        foreach ($pharmacyData as $data) {
            $openingHours = $this->openingHoursParser->parse($data['openingHours']);

            $pharmacy = Pharmacy::create([
                'name'          => $data['name'],
                'opening_hours' => json_encode($openingHours),
                'cash_balance'  => $data['cashBalance'],
            ]);

            foreach ($data['masks'] as $mask) {
                Mask::create([
                    'pharmacy_id' => $pharmacy->id,
                    'name'        => $mask['name'],
                    'price'       => $mask['price'],
                    'quantity'    => 0,
                ]);
            }
        }
    }

    private function importUsers()
    {
        $filePath = storage_path('app/data/users.json');
        $this->info("File path: $filePath");

        if (! file_exists($filePath)) {
            $this->error("File does not exist at path: $filePath");
            return;
        }

        $fileContent = file_get_contents($filePath);
        $this->info("File content:\n" . $fileContent);

        $userData = json_decode($fileContent, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            $this->error('Error decoding JSON: ' . json_last_error_msg());
            return;
        }

        $this->info("Parsed data:\n" . print_r($userData, true));

        foreach ($userData as $data) {
            $user = Member::create([
                'name'         => $data['name'],
                'cash_balance' => $data['cashBalance'],
            ]);

            foreach ($data['purchaseHistories'] as $history) {
                $pharmacy = Pharmacy::where('name', $history['pharmacyName'])->first();
                if (! $pharmacy) {
                    $this->warn("Pharmacy '{$history['pharmacyName']}' not found. Skipping purchase history.");
                    continue;
                }

                $mask = Mask::where('name', $history['maskName'])->where('pharmacy_id', $pharmacy->id)->first();
                if (! $mask) {
                    $this->warn("Mask '{$history['maskName']}' not found in pharmacy '{$history['pharmacyName']}'. Skipping purchase history.");
                    continue;
                }

                PurchaseHistory::create([
                    'user_id'          => $user->id,
                    'pharmacy_id'      => $pharmacy->id,
                    'mask_id'          => $mask->id,
                    'amount'           => $history['transactionAmount'],
                    'transaction_date' => Carbon::parse($history['transactionDate']),
                ]);
            }
        }
    }

}
