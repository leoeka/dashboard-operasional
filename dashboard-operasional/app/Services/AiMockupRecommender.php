<?php

namespace App\Services;

use App\Models\Project;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http; // Untuk hit API AI generik
use OpenAI\Laravel\Facades\OpenAI; // Jika menggunakan SDK OpenAI

// class AiMockupRecommender
// {
//     /**
//      * Metode utama untuk menganalisis dan merekomendasikan mockup
//      */
//     public function recommend(Project $project, Collection $templates): array
//     {
//         $prompt = $this->buildPrompt($project, $templates);

//         // ==========================================================
//         // PILIH DENGAN MUDAH PROVIDER AI YANG INGIN DIGUNAKAN DI SINI
//         // ==========================================================

//         return $this->useOpenAI($prompt);
//         // return $this->useGemini($prompt);
//         // return $this->useDeepSeek($prompt);
//     }

//     /**
//      * Pilihan 1: OpenAI (ChatGPT)
//      */
//     private function useOpenAI(string $prompt): array
//     {
//         try {
//             $response = OpenAI::chat()->create([
//                 'model' => 'gpt-4o-mini',
//                 'messages' => [
//                     ['role' => 'system', 'content' => 'Kamu adalah konsultan web design.'],
//                     ['role' => 'user', 'content' => $prompt],
//                 ],
//                 'response_format' => ['type' => 'json_object'],
//             ]);

//             return json_decode($response->choices[0]->message->content, true);
//         } catch (\Exception $e) {
//             return ['template_id' => null, 'reasoning' => 'Rekomendasi standar berdasarkan tipe proyek.'];
//         }
//     }

//     /**
//      * Pilihan 2: Google Gemini (Contoh jika mau ganti ke Gemini)
//      */
//     private function useGemini(string $prompt): array
//     {
//         try {
//             $apiKey = env('GEMINI_API_KEY');
//             $response = Http::post("https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key={$apiKey}", [
//                 'contents' => [['parts' => [['text' => $prompt]]]]
//             ]);

//             $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
//             return json_decode($text, true) ?? ['template_id' => null, 'reasoning' => 'Rekomendasi Gemini.'];
//         } catch (\Exception $e) {
//             return ['template_id' => null, 'reasoning' => 'Error koneksi AI.'];
//         }
//     }

//     /**
//      * Helper menyusun Prompt
//      */
//     private function buildPrompt(Project $project, Collection $templates): string
//     {
//         return "Pilih 1 ID template terbaik berdasarkan kebutuhan client.
// Client: {$project->client_name}
// Nama Project: {$project->name}
// Tipe Web: {$project->type}

// Daftar Template:
// " . json_encode($templates) . "

// Output HARUS Format JSON murni:
// {\"template_id\": 1, \"reasoning\": \"alasan singkat\"}";
//     }
// }