<?php

use App\Models\Product;
use App\Models\User;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

Artisan::command('warehouse:admin', function () {
    if (User::exists()) {
        $this->error('Akun sudah ada. Gunakan Pengaturan untuk ganti password, atau warehouse:reset-password jika lupa.');

        return 1;
    }
    $name = $this->ask('Nama operator', 'Operator Gudang');
    $username = $this->ask('Username', 'admin');
    $email = $this->ask('Email', 'admin@warehouse.local');
    $password = $this->secret('Password (minimal 12 karakter)');
    $confirmation = $this->secret('Ulangi password');
    $validator = validator(compact('name', 'username', 'email', 'password'), ['name' => 'required|string|max:100', 'username' => 'required|alpha_dash|max:80|unique:users', 'email' => 'required|email|max:150|unique:users', 'password' => 'required|string|min:12']);
    if ($validator->fails() || $password !== $confirmation) {
        $this->error('Data tidak valid atau konfirmasi password berbeda. '.$validator->errors()->first());

        return 1;
    }
    User::create(['name' => $name, 'username' => $username, 'email' => $email, 'password' => Hash::make($password)]);
    $this->info('Akun operator dibuat. Login menggunakan username atau email.');
})->purpose('Membuat satu akun operator tanpa password bawaan.');
Artisan::command('warehouse:reset-password', function () {
    $user = User::first();
    if (! $user) {
        $this->error('Belum ada akun. Jalankan warehouse:admin.');

        return 1;
    }
    if (! $this->confirm('Reset password akun '.$user->username.'?')) {
        return 1;
    }
    $password = $this->secret('Password baru (minimal 12 karakter)');
    $confirmation = $this->secret('Ulangi password');
    if (strlen($password ?? '') < 12 || $password !== $confirmation) {
        $this->error('Password tidak valid.');

        return 1;
    }
    $user->forceFill(['password' => Hash::make($password), 'remember_token' => Str::random(60)])->save();
    $this->info('Password diperbarui. Sesi lama akan diminta login kembali.');
})->purpose('Reset password melalui terminal lokal.');
Artisan::command('warehouse:audit', function () {
    $errors = 0;
    foreach (Product::cursor() as $p) {
        $stock = 0;
        foreach ($p->transactions()->where('status', 'APPROVED')->orderBy('id')->cursor() as $t) {
            if ($t->stock_before !== $stock) {
                $this->error('Rantai histori terputus: '.$t->transaction_number);
                $errors++;
            }$stock = $t->stock_after;
        }
        if ($stock !== $p->stock) {
            $this->error('Stok tidak cocok: '.$p->product_code);
            $errors++;
        }
    }
    if (! $errors) {
        $this->info('OK: seluruh stok cocok dengan rantai histori.');
    }

return $errors ? 1 : 0;
})->purpose('Memverifikasi stok terhadap seluruh histori APPROVED tanpa mengubah data.');
