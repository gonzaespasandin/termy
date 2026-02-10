<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Window;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    /**
     * Listar todos los usuarios
     */
    public function index()
    {
        $users = User::orderBy('name')->get();
        
        // Agregar los nombres de servicios
        $users->transform(function ($user) {
            $user->service_names = $user->getServiceNames();
            return $user;
        });
        
        return response()->json($users);
    }

    /**
     * Crear un nuevo usuario
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|string|email|max:255|unique:users',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,operator',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userData = $request->except('password');
        $userData['password'] = Hash::make($request->password);
        
        // Services se convierte automáticamente a JSON por el cast del modelo
        // Solo asegurarse de que sea un array
        if ($request->has('services')) {
            $userData['services'] = is_array($request->services) ? $request->services : [];
        }

        $user = User::create($userData);
        $user->service_names = $user->getServiceNames();
        
        return response()->json([
            'message' => 'Usuario creado exitosamente',
            'user' => $user
        ], 201);
    }

    /**
     * Mostrar un usuario específico
     */
    public function show($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $user->service_names = $user->getServiceNames();
        
        return response()->json($user);
    }

    /**
     * Actualizar un usuario
     */
    public function update(Request $request, $id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|string|email|max:255|unique:users,email,' . $id,
            'password' => 'sometimes|nullable|string|min:6',
            'role' => 'sometimes|required|in:admin,operator',
            'services' => 'nullable|array',
            'services.*' => 'exists:services,id',
        ]);

        if ($validator->fails()) {
            return response()->json(['errors' => $validator->errors()], 422);
        }

        $userData = $request->except('password');
        
        // Solo actualizar password si se envió
        if ($request->filled('password')) {
            $userData['password'] = Hash::make($request->password);
        }
        
        // Services se convierte automáticamente a JSON por el cast del modelo
        // Solo asegurarse de que sea un array
        if ($request->has('services')) {
            $userData['services'] = is_array($request->services) ? $request->services : [];
        }

        $user->update($userData);
        $user->service_names = $user->getServiceNames();
        
        return response()->json([
            'message' => 'Usuario actualizado exitosamente',
            'user' => $user
        ]);
    }

    /**
     * Eliminar un usuario
     */
    public function destroy($id)
    {
        $user = User::find($id);
        
        if (!$user) {
            return response()->json(['message' => 'Usuario no encontrado'], 404);
        }

        // No permitir eliminar el último admin
        if ($user->isAdmin() && User::where('role', 'admin')->count() === 1) {
            return response()->json([
                'message' => 'No se puede eliminar el único administrador del sistema'
            ], 409);
        }

        $user->delete();
        
        return response()->json([
            'message' => 'Usuario eliminado exitosamente'
        ]);
    }
}

