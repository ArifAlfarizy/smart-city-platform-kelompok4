#!/bin/bash
set -e

echo "========================================"
echo "Python ML Service - Starting..."
echo "========================================"

# Cek apakah model sudah ada
if [ ! -f "models/smartcity_models.pkl" ]; then
    echo "⚠️  Model not found. Generating dataset and training..."
    
    # Generate dataset
    echo "📊 Generating dataset..."
    python generate_dataset.py
    
    # Train model
    echo "🤖 Training models..."
    python train_models.py
    
    if [ -f "models/smartcity_models.pkl" ]; then
        echo "✅ Model training complete!"
    else
        echo "❌ Model training failed!"
        exit 1
    fi
else
    echo "✅ Model found: models/smartcity_models.pkl"
fi

echo "========================================"
echo "🚀 Starting Uvicorn server..."
echo "========================================"

# Jalankan perintah utama
exec "$@"