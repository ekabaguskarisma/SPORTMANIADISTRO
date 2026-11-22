<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\PromoBanner;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class PromoBannerController extends Controller
{
    public function store(Request $request)
    {
        $validated = $request->validate([
            'placement'     => 'required|in:hero,showcase',
            'badge_text'    => 'nullable|string|max:100',
            'bg_typography' => 'nullable|string|max:40',
            'subtitle'      => 'nullable|string|max:120',
            'title'         => 'required|string|max:150',
            'description'   => 'nullable|string|max:1000',
            'button_text'   => 'nullable|string|max:80',
            'button_url'    => 'nullable|string|max:255',
            'media_type'    => 'nullable|in:image,video',
            'image'         => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:20480',
            'video'         => 'nullable|file|mimes:mp4,webm|max:20480',
            'image_caption' => 'nullable|string|max:100',
            'sort_order'    => 'nullable|integer|min:1',
            'is_active'     => 'nullable|boolean',
        ]);

        // Otomatis deteksi apakah file yang diunggah adalah gambar atau video
        $uploadedFile = $request->file('video') ?? $request->file('image');
        $detectedType = $validated['media_type'] ?? 'image';

        if ($uploadedFile) {
            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            $detectedType = in_array($ext, ['mp4', 'webm'], true) ? 'video' : 'image';
            $storedPath = $uploadedFile->store('promos', 'public');

            if ($detectedType === 'video') {
                $validated['video_path'] = $storedPath;
                $validated['image'] = null;
            } else {
                $validated['image'] = $storedPath;
                $validated['video_path'] = null;
            }
        }

        // Jika bg_typography dikosongkan admin, otomatis ambil kata pertama dari badge_text atau title
        $rawTypography = trim((string) ($validated['bg_typography'] ?? ''));
        $validated['bg_typography'] = $rawTypography !== ''
            ? strtoupper($rawTypography)
            : strtoupper(Str::words($validated['badge_text'] ?: $validated['title'], 1, ''));

        // Nilai fallback agar tetap aman jika admin mengosongkan tombol CTA
        $validated['button_text'] = filled($validated['button_text'] ?? null)
            ? trim($validated['button_text'])
            : 'Belanja Sekarang';
        $validated['button_url'] = filled($validated['button_url'] ?? null)
            ? trim($validated['button_url'])
            : '/produk';

        $validated['media_type'] = $detectedType;
        $validated['sort_order'] = $validated['sort_order'] ?? 1;
        $validated['is_active']  = $request->boolean('is_active');

        unset($validated['video']);

        PromoBanner::create($validated);

        return back()->with('success', 'Postingan promo beranda berhasil ditambahkan!');
    }

    public function update(Request $request, PromoBanner $promo)
    {
        $validated = $request->validate([
            'placement'     => 'required|in:hero,showcase',
            'badge_text'    => 'nullable|string|max:100',
            'bg_typography' => 'nullable|string|max:40',
            'subtitle'      => 'nullable|string|max:120',
            'title'         => 'required|string|max:150',
            'description'   => 'nullable|string|max:1000',
            'button_text'   => 'nullable|string|max:80',
            'button_url'    => 'nullable|string|max:255',
            'media_type'    => 'nullable|in:image,video',
            'image'         => 'nullable|file|mimes:jpg,jpeg,png,webp,mp4,webm|max:20480',
            'video'         => 'nullable|file|mimes:mp4,webm|max:20480',
            'image_caption' => 'nullable|string|max:100',
            'sort_order'    => 'nullable|integer|min:1',
            'is_active'     => 'nullable|boolean',
        ]);

        $uploadedFile = $request->file('video') ?? $request->file('image');

        if ($uploadedFile) {
            $this->deleteStoredMedia($promo->image);
            $this->deleteStoredMedia($promo->video_path ?? null);

            $ext = strtolower($uploadedFile->getClientOriginalExtension());
            $detectedType = in_array($ext, ['mp4', 'webm'], true) ? 'video' : 'image';
            $storedPath = $uploadedFile->store('promos', 'public');

            if ($detectedType === 'video') {
                $validated['video_path'] = $storedPath;
                $validated['image'] = null;
            } else {
                $validated['image'] = $storedPath;
                $validated['video_path'] = null;
            }

            $validated['media_type'] = $detectedType;
        }

        $rawTypography = trim((string) ($validated['bg_typography'] ?? ''));
        $validated['bg_typography'] = $rawTypography !== ''
            ? strtoupper($rawTypography)
            : strtoupper(Str::words($validated['badge_text'] ?: $validated['title'], 1, ''));

        $validated['button_text'] = filled($validated['button_text'] ?? null)
            ? trim($validated['button_text'])
            : 'Belanja Sekarang';
        $validated['button_url'] = filled($validated['button_url'] ?? null)
            ? trim($validated['button_url'])
            : '/produk';

        $validated['sort_order'] = $validated['sort_order'] ?? 1;
        $validated['is_active']  = $request->boolean('is_active');

        unset($validated['video']);

        $promo->update($validated);

        return back()->with('success', 'Postingan promo berhasil diperbarui!');
    }

    public function toggle(PromoBanner $promo)
    {
        $promo->update(['is_active' => !$promo->is_active]);

        return back()->with('success', 'Status tampil promo berhasil diubah!');
    }

    public function destroy(PromoBanner $promo)
    {
        $this->deleteStoredMedia($promo->image);
        $this->deleteStoredMedia($promo->video_path ?? null);

        $promo->delete();

        return back()->with('success', 'Postingan promo berhasil dihapus.');
    }

    private function deleteStoredMedia(?string $path): void
    {
        if ($path && !filter_var($path, FILTER_VALIDATE_URL)) {
            Storage::disk('public')->delete($path);
        }
    }
}