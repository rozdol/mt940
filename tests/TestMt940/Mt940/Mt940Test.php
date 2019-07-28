<?php
namespace Test\Rozdol\Mt940;

use Rozdol\Mt940\Mt940;

use PHPUnit\Framework\TestCase;

class Mt940Test extends TestCase
{
    
    protected function setUp()
    {
        //$this->utils = Mt940::getInstance();
        $this->utils = new Mt940();
    }

    /**
    * @dataProvider dataProvider
    */
    public function testUtils($data, $expect)
    {
        $result = $this->utils->payslip($data);
        //fwrite(STDERR, print_r($result, true));
        $this->assertEquals($expect[last_salary], $result[last_salary]);
        $this->assertEquals($expect[annual_taxable_amount], $result[annual_taxable_amount]);
        $this->assertEquals($expect[deductions_total], $result[deductions_total]);
        $this->assertEquals($expect[net_salary], $result[net_salary]);
        $this->assertEquals($expect[contributions_total], $result[contributions_total]);
        $this->assertEquals($expect[cost_to_employer], $result[cost_to_employer]);

        $this->assertEquals($expect[si_tax], $result[employee_pays][0][amount]);
        $this->assertEquals($expect[income_tax], $result[employee_pays][1][amount]);
    }
    public function dataProvider()
    {
        $data=array (
            'date' => '15.06.2018',
            'no' => 0,
            'epmloyee' =>
            array (
                'name' => 'Name',
                'surname' => 'Surname',
                'employer' => 'Company',
                'employer_id' => '1438',
                'possition' => 'Occupation/Possition',
                'sin' => 'SIN No',
                'tax_no' => '',
                'salaries_per_year' => '13',
                'non_resident' => 'f',
                'df' => '01.02.2010',
                'dt' => '01.01.2020',
            ),
            'salaries' =>
            array (
                '01.02.2010' => '5000',
                '01.01.2020' => 0,
            ),
            'deductions' =>
            array (
                'si' =>
                array (
                    'title' => 'Social Insurance tax',
                    'base' => 'm',
                    'calc' =>
                    array (
                        0 => 0.078,
                        4533 => 0,
                    ),
                ),
                'income' =>
                array (
                    'title' => 'Income tax',
                    'base' => 'y',
                    'calc' =>
                    array (
                        0 => 0,
                        19500 => 0.2,
                        28000 => 0.25,
                        36300 => 0.3,
                        60000 => 0.35,
                    ),
                ),
            ),
            'contributions' =>
            array (
                'si' =>
                array (
                    'title' => 'Social Insurance tax',
                    'base' => 'm',
                    'calc' =>
                    array (
                        0 => 0.078,
                        4533 => 0,
                    ),
                ),
                'cohession' =>
                array (
                    'title' => 'Social cohesion fund',
                    'base' => 'm',
                    'calc' =>
                    array (
                        0 => 0.02,
                    ),
                ),
                'hr' =>
                array (
                    'title' => 'Industrial fund',
                    'base' => 'm',
                    'calc' =>
                    array (
                        0 => 0.005,
                        4533 => 0,
                    ),
                ),
                'redund' =>
                array (
                    'title' => 'Redundancy fund',
                    'base' => 'm',
                    'calc' =>
                    array (
                        0 => 0.012,
                        4533 => 0,
                    ),
                ),
            ),
            'allowances' =>
            array (
                '01.04.2010' => '174.59',
            ),
        );

        $expect[last_salary]=5000;
        $expect[annual_taxable_amount]=58308.51;
        $expect[si_tax]=353.57;
        $expect[income_tax]=864.8;
        $expect[deductions_total]=1218.37;
        $expect[net_salary]=3781.63;
        $expect[contributions_total]=530.64;
        $expect[cost_to_employer]=5530.64;

        return [
            [$data,$expect],
        ];
    }
}
