<?php
/**
 * This Software is part of aryelgois\BankInterchange and is provided "as is".
 *
 * @see LICENSE
 */

namespace aryelgois\BankInterchange\BankBillet\Views;

use aryelgois\BankInterchange as BankI;

/**
 * Generates Bank Billets in Caixa Econômica Federal's layout
 *
 * @author Aryel Mota Góis
 * @license MIT
 * @link https://www.github.com/aryelgois/BankInterchange
 */
class CaixaEconomicaFederal extends BankI\Abstracts\Views\BankBillet
{
    /**
     * Procedurally draws the bank billet using FPDF methods
     */
    protected function drawBillet()
    {
        $dict = $this->dictionary;
        
        $this->AddPage();
        
        $this->drawPageHeader();
        
        $this->billetSetFont('cell_data');
        $this->drawDash($dict['payer_receipt']);
        
        $this->drawBillhead();
        
        $this->drawBankHeader();
        
        $this->drawTable1();
        
        $this->billetSetFont('cell_title');
        $this->drawDash($dict['cut_here'], true);
        
        $this->drawBankHeader();
        
        $this->drawTable2();
        
        $this->drawBarCode();
        
        $this->billetSetFont('cell_title');
        $this->drawDash($dict['cut_here'], true);
    }
    
    /**
     * Table 1, stays with the Payer
     *
     * NOTES:
     * - '{{ tax }}' is replaced by the money-formated tax in the demonstrative
     */
    protected function drawTable1()
    {
        $dict = $this->dictionary;
        $model = $this->model;
        $assignor = $this->model->assignor;
        $bank = $this->model->bank;
        $title = $this->model->title;
        $payer = $this->model->title->payer;
        
        /*
         * Structure:
         *
         * Assignor | Agency/Assignor's code | Specie | Amount | Our number
         * Document number | CPF/CNPJ | Due | Document value
         * (-) Discount/Rebates | (-) Other deductions | (+) "Mora"/Fine | (+) Other additions | (=) Amount charged
         * Payer
         */
        $table = [
            [
                ['w' =>  80.8, 'title' => $dict['assignor'],      'data' => $assignor->name],
                ['w' =>  35.4, 'title' => $dict['agency_code'],   'data' => $this->formatAgencyCode()],
                ['w' =>  11,   'title' => $dict['specie'],        'data' => $title->specie['symbol']],
                ['w' =>  16,   'title' => $dict['amount'],        'data' => ''],                                                              // $data['misc']['amount']
                ['w' =>  33.8, 'title' => $dict['onum'],          'data' => $this->formatOnum(),                        'data_align' => 'R']
            ],
            [
                ['w' =>  52.8, 'title' => $dict['doc_number'],    'data' => BankI\Utils::padNumber($title->id, 10)],
                ['w' =>  37,   'title' => $dict['cpf_cnpj'],      'data' => $assignor->formatDocument()],
                ['w' =>  37.4, 'title' => $dict['date_due'],      'data' => static::formatDate($title->due)],
                ['w' =>  49.8, 'title' => $dict['doc_value'],     'data' => $this->formatMoney($this->billet['value']), 'data_align' => 'R']
            ],
            [
                ['w' =>  32,   'title' => $dict['discount'],      'data' => '',                                         'data_align' => 'R'], //$data['misc']['discount']
                ['w' =>  32,   'title' => $dict['deduction'],     'data' => '',                                         'data_align' => 'R'], //$data['misc']['deduction']
                ['w' =>  32,   'title' => $dict['fine'],          'data' => '',                                         'data_align' => 'R'], //$data['misc']['fine']
                ['w' =>  31.2, 'title' => $dict['addition'],      'data' => '',                                         'data_align' => 'R'], //$data['misc']['addition']
                ['w' =>  49.8, 'title' => $dict['charged'],       'data' => '',                                         'data_align' => 'R']  //$data['misc']['charged']
            ],
            [
                ['w' => 177,   'title' => $dict['payer'],         'data' => $payer->name]
            ]
        ];
        foreach ($table as $row) {
            $this->drawTableRow($row);
        }
        
        // Demonstrative ('{{ tax }}' is replaced by the money-formated tax)
        $this->billetSetFont('cell_title');
        $this->Cell(151, 3.5, $dict['demonstrative'], 0, 0);
        $this->Cell(26, 3.5, utf8_decode($dict['mech_auth']), 0, 1);
        $this->billetSetFont('cell_data');
        $y = $this->GetY();
        $this->MultiCell(151, 3.5, utf8_decode(str_replace('{{ tax }}', $this->formatMoney($bank->tax), $this->billet['demonstrative'] ?? '')));
        $y1 = $this->GetY();
        $this->SetXY(161, $y);
        $this->Cell(26, 3.5, '', 0, 1);
        $y2 = $this->GetY();
        $this->SetY(max($y + 14, $y1, $y2));
        $this->Ln(12);
    }
    
    /**
     * Table 2, stays in payment place
     */
    protected function drawTable2()
    {
        $dict = $this->dictionary;
        $model = $this->model;
        $assignor = $this->model->assignor;
        $bank = $this->model->bank;
        $title = $this->model->title;
        $payer = $this->model->title->payer;
        
        $y = $this->GetY(); // get Y to come back and add the aside column
        
        /*
         * Structure:
         *
         * Payment place
         * Assignor
         * Document Date | Document number | Document specie | Accept | Processing Date
         * Bank's use | Wallet | Specie | Amount | Document value UN
         */
        $table = [
            [
                ['w' => 127.2, 'title' => $dict['payment_place'], 'data' => $this->billet['payment_place'] ?? '']
            ],
            [
                ['w' => 127.2, 'title' => $dict['assignor'],      'data' => $assignor->name]
            ],
            [
                ['w' =>  32,   'title' => $dict['date_document'], 'data' => self::formatDate($title->stamp)],
                ['w' =>  42.2, 'title' => $dict['doc_number_sh'], 'data' => BankI\Utils::padNumber($title->id, 10)],
                ['w' =>  18,   'title' => $dict['specie_doc'],    'data' => ''],                                     //$data['misc']['specie_doc']
                ['w' =>  11,   'title' => $dict['accept'],        'data' => ''],                                     //$data['misc']['accept']
                ['w' =>  24,   'title' => $dict['date_process'],  'data' => date('d/m/Y')]
            ],
            [
                ['w' =>  32,   'title' => $dict['bank_use'],      'data' => ''],                                     //$data['misc']['bank_use']
                ['w' =>  24,   'title' => $dict['wallet'],        'data' => $title->wallet['symbol']],
                ['w' =>  16,   'title' => $dict['specie'],        'data' => $title->specie['symbol']],
                ['w' =>  34.2, 'title' => $dict['amount'],        'data' => ''],                                     //$data['misc']['amount']
                ['w' =>  21,   'title' => $dict['doc_value'],     'data' => '']                                      //$data['misc']['value_un']
            ]
        ];
        foreach ($table as $row) {
            $this->drawTableRow($row);
        }
        
        // Instructions
        $y1 = $this->GetY();
        $this->billetSetFont('cell_title');
        $this->Cell(127.2, 7, utf8_decode($dict['instructions']), 0, 1);
        $this->billetSetFont('cell_data');
        $this->MultiCell(127.2, 3.5, utf8_decode($this->billet['instructions'] ?? ''));
        $y2 = $this->GetY();
        
        /**
         * Aside column:
         *
         * Structure:
         *
         * Due
         * Agency/Assignor's code
         * Our number
         * (=) Document value
         * (-) Discount/Rebates
         * (-) Other deductions
         * (+) "Mora"/Fine
         * (+) Other additions
         * (=) Amount charged
         */
        $this->SetY($y);
        $table = [
            ['title' => $dict['date_due'],    'data' => static::formatDate($title->due),            'data_align' => 'R'],
            ['title' => $dict['agency_code'], 'data' => $this->formatAgencyCode(),                  'data_align' => 'R'],
            ['title' => $dict['onum'],        'data' => $this->formatOnum(),                        'data_align' => 'R'],
            ['title' => $dict['doc_value='],  'data' => $this->formatMoney($this->billet['value']), 'data_align' => 'R'],
            ['title' => $dict['discount'],    'data' => '',                                         'data_align' => 'R'], //$data['misc']['discount']
            ['title' => $dict['deduction'],   'data' => '',                                         'data_align' => 'R'], //$data['misc']['deduction']
            ['title' => $dict['fine'],        'data' => '',                                         'data_align' => 'R'], //$data['misc']['fine']
            ['title' => $dict['addition'],    'data' => '',                                         'data_align' => 'R'], //$data['misc']['addition']
            ['title' => $dict['charged'],     'data' => '',                                         'data_align' => 'R']  //$data['misc']['charged']
        ];
        $this->drawTableColumn($table, 137.2, 49.8);
        
        // Instructions border
        $y = $this->GetY();
        $y3 = max($y, $y2);
        $this->Line(10, $y1, 10, $y3);
        $this->Line(10, $y3, 137.2, $y3);
        if ($y3 > $y) {
            $this->Line(137.2, $y3, 137.2, $y);
            $this->SetY($y3);
        }
        
        // Payer
        $this->billetSetFont('cell_title');
        $this->Cell(127.2, 7, $dict['payer'], 'L', 1);
        $this->billetSetFont('cell_data');
        $this->MultiCell(127.2, 3.5, utf8_decode($payer->name . "\n" . $payer->address[0]->outputLong()), 'LB');
        $this->SetXY(137.2, $this->GetY() - 3.5);
        $this->billetSetFont('cell_title');
        $this->Cell(49.8, 3.5, utf8_decode($dict['cod_down']), 'LB', 1);
        
        // Guarantor
        $this->Cell(110, 3.5, $dict['guarantor']);
        $this->Cell(39.5, 3.5, utf8_decode($dict['mech_auth'] . ' - '), 0, 0, 'R');
        $this->billetSetFont('cell_data');
        $this->Cell(27.5, 3.5, utf8_decode($dict['compensation']), 0, 1, 'R');
    }
}