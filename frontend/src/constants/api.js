/**
 * Basis URL API yang dipakai bersama oleh instance axios utama
 * (services/http.js) dan health probe (services/healthService.js) agar
 * gate preload & pemantauan offline selalu mengejar endpoint yang sama.
 */
export const API_BASE_URL = import.meta.env.VITE_API_BASE_URL || 'http://localhost:8000/api'
