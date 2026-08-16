<?php

namespace App\Services\System;

use App\Models\SystemSetting;
use Illuminate\Database\Eloquent\Collection;

class SystemSettingService
{
    public function getAll(): Collection
    {
        return SystemSetting::all();
    }

    public function get(string $key, mixed $default = null): mixed
    {
        $setting = SystemSetting::where('key', $key)->first();
        if (! $setting) {
            return $default;
        }

        return match ($setting->type) {
            'integer' => (int) $setting->value,
            'boolean' => filter_var($setting->value, FILTER_VALIDATE_BOOLEAN),
            'json' => json_decode($setting->value, true),
            default => $setting->value,
        };
    }

    public function set(string $key, mixed $value): SystemSetting
    {
        $setting = SystemSetting::where('key', $key)->first();
        if ($setting) {
            $setting->update(['value' => is_array($value) ? json_encode($value) : (string) $value]);
            return $setting;
        }

        return SystemSetting::create([
            'key' => $key,
            'value' => is_array($value) ? json_encode($value) : (string) $value,
            'type' => is_numeric($value) ? 'integer' : (is_bool($value) ? 'boolean' : 'string'),
        ]);
    }

    public function getKapProfile(): array
    {
        return [
            'kap_name' => $this->get('kap_name', 'KANTOR AKUNTAN PUBLIK DRS. SELAMAT SINURAYA & REKAN'),
            'kap_tagline' => $this->get('kap_tagline', 'Registered Public Accountants & Business Advisors'),
            'kap_license_no' => $this->get('kap_license_no', 'Keputusan Menteri Keuangan No. KEP-234/KM.1/2021'),
            'kap_ojk_no' => $this->get('kap_ojk_no', 'STTD.AP-098/PM.22/2022'),
            'kap_iapi_no' => $this->get('kap_iapi_no', 'Anggota Institut Akuntan Publik Indonesia (IAPI)'),
            'kap_leader_name' => $this->get('kap_leader_name', 'Drs. Selamat Sinuraya, M.Si., Ak., CA., CPA'),
            'kap_leader_license' => $this->get('kap_leader_license', 'AP. 0456'),
            'kap_leader_position' => $this->get('kap_leader_position', 'Pemimpin Rekan (Managing Partner)'),
            'kap_address' => $this->get('kap_address', 'Jl. Setia Budi No. 88 Komplek Bisnis Medan'),
            'kap_city' => $this->get('kap_city', 'Medan, Sumatera Utara'),
            'kap_postal_code' => $this->get('kap_postal_code', '20132'),
            'kap_phone' => $this->get('kap_phone', '(061) 821-4567 / +62 812-6000-8899'),
            'kap_email' => $this->get('kap_email', 'info@kap-sinuraya.com'),
            'kap_website' => $this->get('kap_website', 'www.kap-sinuraya.com'),
            'kap_branch_address' => $this->get('kap_branch_address', 'Kantor Cabang: Gedung Menara Thamrin Lt. 9, Jakarta Pusat'),
            'kap_logo_url' => $this->get('kap_logo_url', '/image/logo.jpeg'),
            'kap_kop_layout' => $this->get('kap_kop_layout', 'standard'),
            'kap_divider_style' => $this->get('kap_divider_style', 'double'),
        ];
    }

    public function updateKapProfile(array $data): array
    {
        $allowedKeys = [
            'kap_name',
            'kap_tagline',
            'kap_license_no',
            'kap_ojk_no',
            'kap_iapi_no',
            'kap_leader_name',
            'kap_leader_license',
            'kap_leader_position',
            'kap_address',
            'kap_city',
            'kap_postal_code',
            'kap_phone',
            'kap_email',
            'kap_website',
            'kap_branch_address',
            'kap_logo_url',
            'kap_kop_layout',
            'kap_divider_style',
        ];

        foreach ($allowedKeys as $key) {
            if (array_key_exists($key, $data)) {
                $this->set($key, $data[$key]);
            }
        }

        return $this->getKapProfile();
    }

    public function uploadLogo(\Illuminate\Http\UploadedFile $file): string
    {
        $extension = $file->getClientOriginalExtension() ?: 'png';
        $filename = 'logo_' . time() . '.' . $extension;
        
        // Save to storage/app/public/branding via local public disk (symlinked)
        $path = $file->storeAs('branding', $filename, 'public');
        
        // Publicly accessible URL via symlink: /storage/branding/logo_xxx.png
        $url = \Illuminate\Support\Facades\Storage::url($path);
        
        // Save to settings
        $this->set('kap_logo_url', $url);
        
        return $url;
    }
}
