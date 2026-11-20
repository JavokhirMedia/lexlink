<?php

namespace Tests\Feature\Ai;

use App\Services\Ai\PiiMasker;
use Tests\TestCase;

class PiiMaskerTest extends TestCase
{
    public function test_masks_names_pinfl_and_identifiers(): void
    {
        $m = new PiiMasker;
        $m->addName('Qodirov Jasur Anvarovich');
        $m->addPinfl('31507901234567');

        $text = 'Javobgar Qodirov Jasur Anvarovich (JShShIR 31507901234567, pasport AC 1234567, tel +998 90 111-22-33), '
            .'avtomobil 01A777BC, uy 10:09:03:01:01:0123. Qodirov J. A.ning daromadi. Qodirovga xabar berilsin. Summa 4 200 000 soʻm.';
        $masked = $m->mask($text);

        $this->assertStringNotContainsString('31507901234567', $masked);
        $this->assertStringNotContainsString('Qodirov', $masked);
        $this->assertStringNotContainsString('Jasur', $masked);
        $this->assertStringNotContainsString('1234567', $masked);
        $this->assertStringNotContainsString('01A777BC', $masked);
        $this->assertStringNotContainsString('10:09:03', $masked);
        $this->assertStringNotContainsString('111-22-33', $masked);
        $this->assertStringContainsString('[SHAXS_1]', $masked);
        $this->assertStringContainsString('[JSHSHIR_1]', $masked);
        $this->assertStringContainsString('[PASPORT_1]', $masked);
        $this->assertStringContainsString('[AVTORAQAM_1]', $masked);
        $this->assertStringContainsString('[KADASTR_1]', $masked);
        $this->assertStringContainsString('[TELEFON_1]', $masked);
        $this->assertStringContainsString('[SHAXS_1]ning', $masked); // case suffix preserved
        $this->assertStringContainsString('4 200 000 soʻm', $masked); // money is not PII
    }

    public function test_unmask_restores_names_but_never_the_full_pinfl(): void
    {
        $m = new PiiMasker;
        $m->addName('Qodirov Jasur Anvarovich');
        $masked = $m->mask('Qodirov Jasur Anvarovich, 31507901234567');

        $restored = $m->unmask($masked);
        $this->assertStringContainsString('Qodirov Jasur Anvarovich', $restored);
        $this->assertStringNotContainsString('31507901234567', $restored);
        $this->assertStringContainsString('3***********67', $restored);
        $this->assertStringContainsString('31507901234567', $m->unmask($masked, revealPinfl: true));
        $this->assertStringNotContainsString('31507901234567', json_encode($m->displayMap()));
    }

    public function test_apostrophe_variants_and_unknown_patronymic_names(): void
    {
        $m = new PiiMasker;
        $m->addName("G'aniyev O'tkir");
        $masked = $m->mask('Gʻaniyev Oʻtkir va guvoh Rahimova Dilnoza Karimovna hamda Ergashev Sardor Akmal oʻgʻli keldi.');

        $this->assertStringNotContainsString('aniyev', $masked);
        $this->assertStringNotContainsString('Rahimova', $masked);
        $this->assertStringNotContainsString('Ergashev', $masked);
        $this->assertSame(3, preg_match_all('/\[SHAXS_\d\]/', $masked));
    }

    public function test_mask_array_masks_pii_keys_and_keeps_organisations(): void
    {
        $m = new PiiMasker;
        $out = $m->maskArray([
            'subject' => ['pinfl' => '315*********67', 'full_name' => 'Qodirov J.'],
            'employers' => [['name' => '"Toshkent Tekstil" MChJ', 'tin' => '301234567']],
            'objects' => [['cadastral_number' => '10:09:03:01:01:0123', 'address' => 'Toshkent sh., Chilonzor 5-uy', 'area' => 54.2]],
            'note' => 'JShShIR 31507901234567',
        ]);
        $json = json_encode($out, JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString('Qodirov', $json);
        $this->assertStringNotContainsString('315*', $json);
        $this->assertStringNotContainsString('Chilonzor', $json);
        $this->assertStringNotContainsString('10:09:03', $json);
        $this->assertStringNotContainsString('31507901234567', $json);
        $this->assertStringContainsString('Toshkent Tekstil', $json);
        $this->assertSame(54.2, $out['objects'][0]['area']);
    }
}
