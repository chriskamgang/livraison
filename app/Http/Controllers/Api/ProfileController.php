<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class ProfileController extends Controller
{
    // GET /api/profile
    public function show(Request $request)
    {
        return response()->json($request->user()->load('addresses'));
    }

    // PUT /api/profile
    public function update(Request $request)
    {
        $validated = $request->validate([
            'name'      => 'sometimes|string|max:255',
            'first_name'=> 'sometimes|string|max:255',
            'last_name' => 'sometimes|string|max:255',
            'phone'     => 'sometimes|string|unique:users,phone,' . $request->user()->id,
            'fcm_token' => 'sometimes|string',
        ]);

        $request->user()->update($validated);

        return response()->json(['message' => 'Profil mis à jour.', 'user' => $request->user()->fresh()]);
    }

    // POST /api/profile/avatar
    public function uploadAvatar(Request $request)
    {
        $request->validate(['avatar' => 'required|image|max:3072']);

        $path = $request->file('avatar')->store('avatars', 'public');
        $request->user()->update(['avatar' => $path]);

        return response()->json(['message' => 'Photo de profil mise à jour.', 'avatar' => $path]);
    }

    // GET /api/profile/addresses
    public function addresses(Request $request)
    {
        return response()->json($request->user()->addresses()->get());
    }

    // POST /api/profile/addresses
    public function addAddress(Request $request)
    {
        $validated = $request->validate([
            'label'           => 'required|string|max:100',
            'address'         => 'required|string',
            'address_details' => 'nullable|string',
            'latitude'        => 'nullable|numeric|between:-90,90',
            'longitude'       => 'nullable|numeric|between:-180,180',
            'city'            => 'nullable|string',
            'is_default'      => 'nullable|boolean',
            'phone'           => 'nullable|string|max:20',
        ]);

        // Si nouvelle adresse par défaut, désactiver les autres
        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address = $request->user()->addresses()->create($validated);

        return response()->json(['message' => 'Adresse ajoutée.', 'address' => $address], 201);
    }

    // PUT /api/profile/addresses/{id}
    public function updateAddress(Request $request, $id)
    {
        $address = $request->user()->addresses()->findOrFail($id);

        $validated = $request->validate([
            'label'           => 'sometimes|string|max:100',
            'address'         => 'sometimes|string',
            'address_details' => 'nullable|string',
            'latitude'        => 'sometimes|numeric',
            'longitude'       => 'sometimes|numeric',
            'is_default'      => 'nullable|boolean',
            'phone'           => 'nullable|string|max:20',
        ]);

        if (!empty($validated['is_default'])) {
            $request->user()->addresses()->update(['is_default' => false]);
        }

        $address->update($validated);

        return response()->json(['message' => 'Adresse mise à jour.', 'address' => $address]);
    }

    // DELETE /api/profile/addresses/{id}
    public function deleteAddress(Request $request, $id)
    {
        $request->user()->addresses()->findOrFail($id)->delete();
        return response()->json(['message' => 'Adresse supprimée.']);
    }

    // POST /api/profile/addresses/{id}/default
    public function setDefaultAddress(Request $request, $id)
    {
        // Désactiver toutes les adresses par défaut
        $request->user()->addresses()->update(['is_default' => false]);

        // Définir la nouvelle adresse par défaut
        $address = $request->user()->addresses()->findOrFail($id);
        $address->update(['is_default' => true]);

        return response()->json(['message' => 'Adresse par défaut mise à jour.', 'address' => $address]);
    }

    // GET /api/notifications
    public function notifications(Request $request)
    {
        $notifs = \App\Models\CustomNotification::where('user_id', $request->user()->id)
            ->latest()
            ->paginate(20);

        return response()->json($notifs);
    }

    // POST /api/notifications/{id}/read
    public function markNotificationRead(Request $request, $id)
    {
        \App\Models\CustomNotification::where('user_id', $request->user()->id)
            ->findOrFail($id)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Notification marquée comme lue.']);
    }

    // POST /api/notifications/read-all
    public function markAllNotificationsRead(Request $request)
    {
        \App\Models\CustomNotification::where('user_id', $request->user()->id)
            ->where('is_read', false)
            ->update(['is_read' => true, 'read_at' => now()]);

        return response()->json(['message' => 'Toutes les notifications lues.']);
    }
}
