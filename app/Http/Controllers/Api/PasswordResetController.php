<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\WhatsAppService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

/**
 * Réinitialisation du mot de passe depuis l'app mobile, par code à 6 chiffres.
 * Le code est envoyé par WhatsApp (si le compte a un téléphone) et par email.
 * Il est stocké haché dans password_reset_tokens et expire après 15 minutes.
 */
class PasswordResetController extends Controller
{
    private const CODE_TTL_MINUTES = 15;

    // POST /api/auth/password/forgot  { email }
    public function forgot(Request $request, WhatsAppService $whatsApp)
    {
        $request->validate(['email' => 'required|email']);
        $email = strtolower(trim($request->email));

        // Réponse identique que le compte existe ou non (ne révèle pas les emails inscrits)
        $genericResponse = response()->json([
            'message' => 'Si un compte existe avec cet email, un code de vérification vient de vous être envoyé par WhatsApp et/ou email.',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [$email])->first();
        if (!$user || $user->status === 'suspended') {
            return $genericResponse;
        }

        $code = (string) random_int(100000, 999999);

        DB::table('password_reset_tokens')->updateOrInsert(
            ['email' => $user->email],
            ['token' => Hash::make($code), 'created_at' => now()]
        );

        $text = "Estuaire Eat : votre code de réinitialisation est {$code}. "
              . 'Il expire dans ' . self::CODE_TTL_MINUTES . ' minutes. Ne le partagez avec personne.';

        if ($user->phone) {
            $whatsApp->sendMessage($user->phone, "🔐 {$text}");
        }

        try {
            Mail::raw($text, function ($message) use ($user) {
                $message->to($user->email)->subject('Votre code de réinitialisation Estuaire Eat');
            });
        } catch (\Throwable $e) {
            Log::warning('Password reset mail failed', ['email' => $user->email, 'error' => $e->getMessage()]);
        }

        return $genericResponse;
    }

    // POST /api/auth/password/reset  { email, code, password }
    public function reset(Request $request)
    {
        $validated = $request->validate([
            'email'    => 'required|email',
            'code'     => 'required|digits:6',
            'password' => 'required|string|min:6',
        ]);

        $user = User::whereRaw('LOWER(email) = ?', [strtolower(trim($validated['email']))])->first();
        $row = $user ? DB::table('password_reset_tokens')->where('email', $user->email)->first() : null;

        $invalid = response()->json(['message' => 'Code invalide ou expiré.'], 422);

        if (!$row || !Hash::check($validated['code'], $row->token)) {
            return $invalid;
        }
        if (now()->diffInMinutes($row->created_at, true) > self::CODE_TTL_MINUTES) {
            DB::table('password_reset_tokens')->where('email', $user->email)->delete();
            return $invalid;
        }

        $user->password = Hash::make($validated['password']);
        $user->save();

        DB::table('password_reset_tokens')->where('email', $user->email)->delete();

        // Déconnecte les autres appareils et ouvre directement une session
        $user->tokens()->delete();
        $token = $user->createToken($user->role . '-token')->plainTextToken;

        return response()->json([
            'message' => 'Mot de passe modifié avec succès.',
            'user'    => $user,
            'token'   => $token,
        ]);
    }
}
