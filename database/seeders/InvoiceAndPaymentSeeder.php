<?php

namespace Database\Seeders;

use App\Models\FeeType;
use App\Models\Invoice;
use App\Models\InvoiceItem;
use App\Models\Payment;
use App\Models\Student;
use App\Models\User;
use Illuminate\Database\Seeder;

class InvoiceAndPaymentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('Creating invoices and payments...');

        // Get necessary data
        $students = Student::with('user')->get();
        $accountants = User::role('accountant')->get();
        $feeTypes = FeeType::all();

        if ($students->isEmpty() || $accountants->isEmpty() || $feeTypes->isEmpty()) {
            $this->command->error('Missing required data for seeding invoices and payments.');
            return;
        }

        $progressBar = $this->command->getOutput()->createProgressBar($students->count());
        $progressBar->start();

        foreach ($students as $student) {
            // Create 1-3 invoices per student
            $numberOfInvoices = rand(1, 3);

            for ($i = 0; $i < $numberOfInvoices; $i++) {
                $invoice = Invoice::factory()->create([
                    'student_id' => $student->id,
                    'issued_by' => $accountants->random()->id,
                ]);

                // Add 1-3 items to each invoice
                $numberOfItems = rand(1, 3);
                $totalAmount = 0;

                for ($j = 0; $j < $numberOfItems; $j++) {
                    $amount = fake()->numberBetween(500000, 2000000);
                    $totalAmount += $amount;

                    $feeType = $feeTypes->random();
                    InvoiceItem::create([
                        'invoice_id' => $invoice->id,
                        'fee_type_id' => $feeType->id,
                        'description' => $feeType->name . ' - ' . now()->format('m/Y'),
                        'unit_price' => $amount,
                        'quantity' => 1,
                        'total_amount' => $amount,
                        'note' => 'Random generated fee'
                    ]);
                }

                // Update invoice total amount
                $invoice->update(['total_amount' => $totalAmount]);

                // Create 0-2 payments for this invoice
                $numberOfPayments = rand(0, 2);
                $paidAmount = 0;

                for ($k = 0; $k < $numberOfPayments; $k++) {
                    $remainingAmount = $totalAmount - $paidAmount;
                    if ($remainingAmount <= 0) break;

                    // Either pay full remaining amount or a portion
                    $paymentAmount = fake()->boolean(30) ?
                        $remainingAmount : // Full remaining amount (30% chance)
                        fake()->numberBetween(100000, min($remainingAmount, 1000000)); // Partial amount

                    $payment = Payment::factory()->create([
                        'invoice_id' => $invoice->id,
                        'amount_paid' => $paymentAmount,
                        'payer_user_id' => $student->guardians->random()->guardian_user_id ?? $student->user_id,
                        'created_by_user_id' => $accountants->random()->id,
                    ]);

                    $paidAmount += $paymentAmount;
                }

                // Update invoice paid amount and status
                $invoice->update([
                    'paid_amount' => $paidAmount,
                    'status' => $paidAmount >= $totalAmount ? 'paid' : ($paidAmount > 0 ? 'partial' : 'unpaid'),
                ]);
            }

            $progressBar->advance();
        }

        $progressBar->finish();
        $this->command->info("\nInvoices and payments created successfully.");
    }
}
