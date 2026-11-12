<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_halaman_utama_mengalihkan_tamu_ke_login(): void
    {
        $this->get('/')->assertRedirect('/login');
    }

    public function test_halaman_login_terbuka(): void
    {
        $this->get('/login')->assertOk();
    }

    public function test_pemakaian_butuh_login(): void
    {
        $this->get('/pemakaian')->assertRedirect('/login');
        $this->get('/pemakaian/export')->assertRedirect('/login');
    }

    public function test_halaman_baru_butuh_login(): void
    {
        foreach (['/alert', '/kesehatan', '/log'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
    }

    public function test_grafik_dan_pengaturan_butuh_login(): void
    {
        foreach (['/grafik', '/pengaturan'] as $url) {
            $this->get($url)->assertRedirect('/login');
        }
        $this->post('/pengaturan/sandi')->assertRedirect('/login');
    }
}
