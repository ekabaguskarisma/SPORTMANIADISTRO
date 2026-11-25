<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\SiteSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class HomeSectionController extends Controller
{
    public function index(): View
    {
        return view('admin.home-sections.index', [
            'siteIdentity' => SiteSetting::getJson('site_identity', $this->defaultSiteIdentity()),
            'trustedBrands' => SiteSetting::getJson('home_trusted_brands', $this->defaultTrustedBrands()),
            'heroSlides' => SiteSetting::getJson('home_hero_slides', $this->defaultHeroSlides()),
            'curatedCategories' => SiteSetting::getJson('home_curated_categories', $this->defaultCuratedCategories()),
            'featuredShowcase' => SiteSetting::getJson('home_featured_showcase', $this->defaultFeaturedShowcase()),
            'storeLocation' => SiteSetting::getJson('store_location', $this->defaultStoreLocation()),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'identity.site_name' => ['required', 'string', 'max:60'],
            'identity.site_tagline' => ['nullable', 'string', 'max:60'],
            'identity.site_code' => ['required', 'string', 'max:6'],
            'identity.site_logo' => ['nullable', 'string', 'max:255'],
            'identity.remove_logo' => ['nullable'],
            'identity.logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],

            'brands' => ['required', 'array', 'min:1'],
            'brands.*.code' => ['required', 'string', 'max:20'],
            'brands.*.name' => ['required', 'string', 'max:60'],
            'brands.*.subtitle' => ['nullable', 'string', 'max:80'],
            'brands.*.logo' => ['nullable', 'string', 'max:255'],
            'brands.*.existing_logo' => ['nullable', 'string', 'max:255'],
            'brands.*.remove_logo' => ['nullable'],
            'brands.*.logo_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp,svg', 'max:5120'],

            'slides' => ['required', 'array', 'min:1'],
            'slides.*.badge' => ['nullable', 'string', 'max:60'],
            'slides.*.kicker' => ['nullable', 'string', 'max:60'],
            'slides.*.title' => ['required', 'string', 'max:80'],
            'slides.*.description' => ['nullable', 'string', 'max:255'],
            'slides.*.cta_text' => ['nullable', 'string', 'max:40'],
            'slides.*.cta_url' => ['nullable', 'string', 'max:255'],
            'slides.*.image' => ['nullable', 'string', 'max:255'],
            'slides.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'categories' => ['required', 'array', 'min:1'],
            'categories.*.tag' => ['nullable', 'string', 'max:50'],
            'categories.*.title' => ['required', 'string', 'max:50'],
            'categories.*.subtitle' => ['nullable', 'string', 'max:90'],
            'categories.*.url' => ['nullable', 'string', 'max:255'],
            'categories.*.image' => ['nullable', 'string', 'max:255'],
            'categories.*.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'showcase.badge' => ['nullable', 'string', 'max:60'],
            'showcase.title' => ['required', 'string', 'max:80'],
            'showcase.description' => ['nullable', 'string', 'max:255'],
            'showcase.cta_text' => ['nullable', 'string', 'max:50'],
            'showcase.cta_url' => ['nullable', 'string', 'max:255'],
            'showcase.image' => ['nullable', 'string', 'max:255'],
            'showcase.image_file' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],

            'location.free_shipping_enabled' => ['nullable'],
            'location.min_order_free_shipping_enabled' => ['nullable'],
            'location.min_order_free_shipping_amount' => ['required', 'numeric', 'min:0'],
            'location.store_address' => ['required', 'string', 'max:500'],
            'location.store_lat' => ['required', 'numeric', 'between:-90,90'],
            'location.store_lng' => ['required', 'numeric', 'between:-180,180'],
            'location.free_shipping_radius_km' => ['required', 'numeric', 'min:0', 'max:100'],
            'location.google_maps_url' => ['nullable', 'url', 'max:500'],
        ]);

        // Simpan Identitas & Logo Toko
        $identityInput = $validated['identity'];
        $siteLogoPath = filled($identityInput['site_logo'] ?? null) ? trim((string) $identityInput['site_logo']) : null;

        if (!empty($identityInput['remove_logo'])) {
            if ($siteLogoPath && Storage::disk('public')->exists($siteLogoPath)) {
                Storage::disk('public')->delete($siteLogoPath);
            }
            $siteLogoPath = null;
        }

        if ($request->hasFile('identity.logo_file') && $request->file('identity.logo_file')->isValid()) {
            if ($siteLogoPath && Storage::disk('public')->exists($siteLogoPath)) {
                Storage::disk('public')->delete($siteLogoPath);
            }
            $siteLogoPath = $request->file('identity.logo_file')->store('home-sections/identity', 'public');
        }

        SiteSetting::setJson('site_identity', [
            'site_name' => trim($identityInput['site_name']),
            'site_tagline' => trim($identityInput['site_tagline'] ?? 'DISTRO ADMIN'),
            'site_code' => strtoupper(trim($identityInput['site_code'])),
            'site_logo' => $siteLogoPath,
        ]);

        // Simpan Trusted Brands
        $brands = [];
        foreach (array_values($validated['brands']) as $idx => $brand) {
            $logoPath = $brand['logo'] ?? ($brand['existing_logo'] ?? null);
            $logoPath = filled($logoPath) ? trim((string) $logoPath) : null;

            if (!empty($brand['remove_logo'])) {
                if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                    Storage::disk('public')->delete($logoPath);
                }
                $logoPath = null;
            }

            $uploadedLogo = $request->file("brands.$idx.logo_file") ?? $request->file("brands.$idx.logo");
            if ($uploadedLogo && $uploadedLogo->isValid()) {
                if ($logoPath && Storage::disk('public')->exists($logoPath)) {
                    Storage::disk('public')->delete($logoPath);
                }
                $logoPath = $uploadedLogo->store('home-sections/brands', 'public');
            }

            $brands[] = [
                'code' => trim($brand['code']),
                'name' => trim($brand['name']),
                'subtitle' => trim($brand['subtitle'] ?? ''),
                'logo' => $logoPath,
            ];
        }

        // Simpan Hero Slides
        $slides = [];
        foreach (array_values($validated['slides']) as $idx => $slide) {
            $imagePath = $slide['image'] ?? '';
            if ($request->hasFile("slides.$idx.image_file")) {
                $imagePath = $request->file("slides.$idx.image_file")
                    ->store('home-sections/slides', 'public');
            }
            $slides[] = [
                'badge' => trim($slide['badge'] ?? ''),
                'kicker' => trim($slide['kicker'] ?? ''),
                'title' => trim($slide['title']),
                'description' => trim($slide['description'] ?? ''),
                'cta_text' => trim($slide['cta_text'] ?? 'JELAJAHI KOLEKSI →'),
                'cta_url' => trim($slide['cta_url'] ?? '#katalog'),
                'image' => $imagePath,
            ];
        }

        // Simpan Curated Categories
        $categories = [];
        foreach (array_values($validated['categories']) as $idx => $cat) {
            $imagePath = $cat['image'] ?? '';
            if ($request->hasFile("categories.$idx.image_file")) {
                $imagePath = $request->file("categories.$idx.image_file")
                    ->store('home-sections/categories', 'public');
            }
            $categories[] = [
                'tag' => trim($cat['tag'] ?? ''),
                'title' => trim($cat['title']),
                'subtitle' => trim($cat['subtitle'] ?? ''),
                'url' => trim($cat['url'] ?? '#katalog'),
                'image' => $imagePath,
            ];
        }

        // Simpan Featured Showcase
        $showcase = $validated['showcase'];
        $showcaseImage = $showcase['image'] ?? '';
        if ($request->hasFile('showcase.image_file')) {
            $showcaseImage = $request->file('showcase.image_file')
                ->store('home-sections/showcase', 'public');
        }

        // Simpan Lokasi Store & Pengaturan Promo Ongkir
        $loc = $validated['location'];
        $freeShippingEnabled = $request->boolean('location.free_shipping_enabled');
        $minOrderFreeShippingEnabled = $request->boolean('location.min_order_free_shipping_enabled');

        SiteSetting::setJson('home_trusted_brands', $brands);
        SiteSetting::setJson('home_hero_slides', $slides);
        SiteSetting::setJson('home_curated_categories', $categories);
        SiteSetting::setJson('home_featured_showcase', [
            'badge' => trim($showcase['badge'] ?? 'FEATURED SHOWCASE'),
            'title' => trim($showcase['title']),
            'description' => trim($showcase['description'] ?? ''),
            'cta_text' => trim($showcase['cta_text'] ?? 'BELANJA KATEGORI →'),
            'cta_url' => trim($showcase['cta_url'] ?? '#katalog'),
            'image' => $showcaseImage,
        ]);
        SiteSetting::setJson('store_location', [
            'free_shipping_enabled' => $freeShippingEnabled,
            'min_order_free_shipping_enabled' => $minOrderFreeShippingEnabled,
            'min_order_free_shipping_amount' => (float) $loc['min_order_free_shipping_amount'],
            'store_address' => trim($loc['store_address']),
            'store_lat' => (float) $loc['store_lat'],
            'store_lng' => (float) $loc['store_lng'],
            'free_shipping_radius_km' => (float) $loc['free_shipping_radius_km'],
            'google_maps_url' => trim($loc['google_maps_url'] ?? ''),
        ]);

        return back()->with('success', 'Logo, identitas toko, lokasi store, dan pengaturan promo ongkir berhasil diperbarui!');
    }

    private function defaultSiteIdentity(): array
    {
        return [
            'site_name' => 'SPORT MANIA',
            'site_tagline' => 'DISTRO ADMIN',
            'site_code' => 'SM',
            'site_logo' => null,
        ];
    }

    private function defaultTrustedBrands(): array
    {
        return [
            ['code' => 'SM', 'name' => 'SPORT MANIA', 'subtitle' => 'Main Court Apparel', 'logo' => null],
            ['code' => 'NIKE', 'name' => 'PRO COMBAT', 'subtitle' => 'Authorized Partner', 'logo' => null],
            ['code' => 'ADIDAS', 'name' => 'PERFORMANCE', 'subtitle' => 'Official Licensee', 'logo' => null],
            ['code' => 'PUMA', 'name' => 'SPEED SERIES', 'subtitle' => 'Verified Supplier', 'logo' => null],
        ];
    }

    private function defaultHeroSlides(): array
    {
        return [
            [
                'badge' => '#1 BEST SELLER',
                'kicker' => 'PERFORMANCE APPAREL',
                'title' => 'PRO RUNNING 2026',
                'description' => 'Dirancang dengan teknologi aerodinamis ringan untuk memaksimalkan kecepatan lari tanpa kompromi.',
                'cta_text' => 'JELAJAHI KOLEKSI →',
                'cta_url' => '#katalog',
                'image' => '',
            ],
            [
                'badge' => '#2 HIGH PERFORMANCE',
                'kicker' => 'MAXIMUM STRENGTH',
                'title' => 'ELITE TRAINING',
                'description' => 'Dukungan perlengkapan angkat beban profesional untuk menjaga stabilitas dan performa latihan maksimal.',
                'cta_text' => 'JELAJAHI KOLEKSI →',
                'cta_url' => '#katalog',
                'image' => '',
            ],
        ];
    }

    private function defaultCuratedCategories(): array
    {
        return [
            ['tag' => 'ELEVATED & ACTIVE', 'title' => 'WOMEN', 'subtitle' => 'Koleksi Aktif & Stylish', 'url' => '#katalog', 'image' => ''],
            ['tag' => 'STREETWEAR & SPORT', 'title' => 'MEN', 'subtitle' => 'Performa Maskulin & Modern', 'url' => '#katalog', 'image' => ''],
            ['tag' => 'HIGH PERFORMANCE', 'title' => 'SPORTS', 'subtitle' => 'Gear Profesional untuk Atlit Juara', 'url' => '#katalog', 'image' => ''],
            ['tag' => 'DAILY COMFORT', 'title' => 'LIFESTYLE', 'subtitle' => 'Kenyamanan Maksimal Setiap Hari', 'url' => '#katalog', 'image' => ''],
        ];
    }

    private function defaultFeaturedShowcase(): array
    {
        return [
            'badge' => 'FEATURED SHOWCASE',
            'title' => 'KOLEKSI EKSKLUSIF WOMEN',
            'description' => 'Eksklusif Koleksi Wanita Aktif & Stylish. Dirancang khusus dengan standar material premium untuk memberikan kenyamanan dan estetika visual tingkat tinggi.',
            'cta_text' => 'BELANJA KATEGORI WOMEN →',
            'cta_url' => '#katalog',
            'image' => '',
        ];
    }

    private function defaultStoreLocation(): array
    {
        return [
            'free_shipping_enabled' => true,
            'min_order_free_shipping_enabled' => true,
            'min_order_free_shipping_amount' => 250000,
            'store_address' => 'Jl. MT. Haryono No.129, Dinoyo, Kec. Lowokwaru, Kota Malang, Jawa Timur 65144',
            'store_lat' => -7.9398000,
            'store_lng' => 112.6083000,
            'free_shipping_radius_km' => 10,
            'google_maps_url' => 'https://maps.google.com/?q=-7.9398,112.6083',
        ];
    }
}