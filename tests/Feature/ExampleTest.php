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

    public function test_login_page_is_english_and_shows_location_name(): void
    {
        config(['app.location_name' => 'Pump Station 3']);

        $this->get('/login')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Sign in')
            ->assertSee('Pump Station 3')
            ->assertSee('Show')
            ->assertDontSee('Masuk')
            ->assertDontSee('Kata sandi');
    }

    public function test_login_shows_english_validation_messages(): void
    {
        $this->from('/login')->post('/login', [])
            ->assertRedirect('/login')
            ->assertSessionHasErrors(['username' => 'Please enter your username.']);
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
