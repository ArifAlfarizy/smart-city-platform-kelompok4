FROM python:3.11-slim

WORKDIR /app

RUN apt-get update && apt-get install -y --no-install-recommends \
    gcc \
    curl \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY . .

# TRAINING 3 MODELS

# Model 1 — Congestion Classifier (existing)
RUN python generate_dataset.py && python train_models.py

# Model 2 — Volume Predictor
RUN python generate_volume_dataset.py && python train_volume_model.py

# Model 3 — Incident Risk Predictor
RUN python generate_risk_dataset.py && python train_risk_model.py

EXPOSE 5000

HEALTHCHECK --interval=30s --timeout=10s --start-period=60s --retries=3 \
    CMD curl -f http://localhost:5000/health || exit 1

CMD ["uvicorn", "main:app", "--host", "0.0.0.0", "--port", "5000"]