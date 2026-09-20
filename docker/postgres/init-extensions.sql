-- earthdistance (dan cube, dependensinya) dipakai StoreRepository untuk
-- mengurutkan toko berdasarkan jarak. Keduanya bukan extension "trusted",
-- jadi hanya superuser yang boleh membuatnya -- karena itu dibuat di sini,
-- pada init database, bukan lewat migrasi Laravel yang jalan sebagai role
-- aplikasi. Di shared-postgres produksi langkah setara dijalankan sekali
-- oleh role postgres saat provisioning database blukios.
CREATE EXTENSION IF NOT EXISTS cube;
CREATE EXTENSION IF NOT EXISTS earthdistance;
