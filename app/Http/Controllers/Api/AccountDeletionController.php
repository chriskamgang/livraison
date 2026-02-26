<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\AccountDeletionRequest;
use App\Models\User;
use App\Notifications\AccountDeletionRequestNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class AccountDeletionController extends Controller
{
    /**
     * Submit an account deletion request
     */
    public function requestDeletion(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
            'reason' => 'nullable|string',
            'additional_comments' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        // Check if user exists
        $user = null;
        if ($request->email) {
            $user = User::where('email', $request->email)->first();
        } elseif ($request->phone) {
            $user = User::where('phone', $request->phone)->first();
        }

        if (!$user) {
            return response()->json([
                'success' => false,
                'message' => 'Aucun compte trouvé avec ces informations'
            ], 404);
        }

        // Check if there's already a pending request
        $existingRequest = AccountDeletionRequest::where(function($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            }
            if ($request->phone) {
                $query->where('phone', $request->phone);
            }
        })
        ->where('status', 'pending')
        ->first();

        if ($existingRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Une demande de suppression est déjà en cours pour ce compte'
            ], 409);
        }

        // Create the deletion request
        $deletionRequest = AccountDeletionRequest::create([
            'email' => $request->email,
            'phone' => $request->phone,
            'reason' => $request->reason,
            'additional_comments' => $request->additional_comments,
            'ip_address' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'status' => 'pending',
        ]);

        // Send notification email to user
        try {
            $user->notify(new AccountDeletionRequestNotification($deletionRequest));
        } catch (\Exception $e) {
            // Log error but don't fail the request
            \Log::error('Failed to send deletion request notification: ' . $e->getMessage());
        }

        return response()->json([
            'success' => true,
            'message' => 'Votre demande de suppression a été enregistrée avec succès. Vous recevrez un email de confirmation.',
            'request_id' => $deletionRequest->id
        ], 201);
    }

    /**
     * Check the status of an account deletion request
     */
    public function checkStatus(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'nullable|email|required_without:phone',
            'phone' => 'nullable|string|required_without:email',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Données invalides',
                'errors' => $validator->errors()
            ], 422);
        }

        $deletionRequest = AccountDeletionRequest::where(function($query) use ($request) {
            if ($request->email) {
                $query->where('email', $request->email);
            }
            if ($request->phone) {
                $query->where('phone', $request->phone);
            }
        })
        ->latest()
        ->first();

        if (!$deletionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune demande de suppression trouvée'
            ], 404);
        }

        return response()->json([
            'success' => true,
            'request' => [
                'id' => $deletionRequest->id,
                'status' => $deletionRequest->status,
                'created_at' => $deletionRequest->created_at->format('d/m/Y H:i'),
                'processed_at' => $deletionRequest->processed_at ? $deletionRequest->processed_at->format('d/m/Y H:i') : null,
            ]
        ]);
    }

    /**
     * Cancel a pending account deletion request (authenticated users only)
     */
    public function cancelRequest(Request $request)
    {
        $user = $request->user();

        $deletionRequest = AccountDeletionRequest::where(function($query) use ($user) {
            $query->where('email', $user->email)
                  ->orWhere('phone', $user->phone);
        })
        ->where('status', 'pending')
        ->latest()
        ->first();

        if (!$deletionRequest) {
            return response()->json([
                'success' => false,
                'message' => 'Aucune demande de suppression en attente trouvée'
            ], 404);
        }

        $deletionRequest->update([
            'status' => 'rejected',
            'admin_notes' => 'Annulée par l\'utilisateur',
            'processed_at' => now(),
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Votre demande de suppression a été annulée avec succès'
        ]);
    }
}
