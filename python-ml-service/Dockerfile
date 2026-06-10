# python-ml-service/Dockerfile
# Base image slim untuk efisiensi ukuran
FROM python:3.11-slim

# Set working directory
WORKDIR /app

# Install system dependency yang dibutuhkan scikit-learn & pika
RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    curl \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements dulu (layer caching — kalau requirements tidak berubah,
# layer ini tidak di-rebuild ulang saat ada perubahan kode)
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# Copy seluruh source code
COPY . .

# Train model saat build image
# (akan diimplementasikan penuh di Sprint 2 setelah train_models.py selesai)
# RUN python train_models.py

# Expose port FastAPI
EXPOSE 5000

# Health check — cek endpoint /health setiap 30 detik
HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:5000/health || exit 1

# Jalankan FastAPI dengan uvicorn
CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]