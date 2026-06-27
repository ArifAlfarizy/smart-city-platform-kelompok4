import "dotenv/config";

const num = (v, fallback) => (v ? Number(v) : fallback);    
export const services = {
  auth: process.env.AUTH_SERVICE_URL || "http://localhost:3001",
  traffic: process.env.TRAFFIC_SERVICE_URL || "http://localhost:8001",
  environment: process.env.ENVIRONMENT_SERVICE_URL || "http://localhost:8002",
  citizen: process.env.CITIZEN_SERVICE_URL || "http://localhost:8080",
  ml: process.env.ML_SERVICE_URL || "http://localhost:5000",
};


export const dashboardPaths = {
  trafficSummary: process.env.TRAFFIC_SUMMARY_PATH || "/api/traffic/current",
  environmentStatus:
    process.env.ENVIRONMENT_STATUS_PATH || "/api/environment/current",
  environmentFlood:
    process.env.ENVIRONMENT_FLOOD_PATH || "/api/environment/alerts",
  citizenIncidents:
    process.env.CITIZEN_INCIDENTS_PATH || "/api/citizens/reports/all",
};

export const GATEWAY_PORT = num(process.env.PORT, 3000);
export const FETCH_TIMEOUT_MS = num(process.env.DASHBOARD_FETCH_TIMEOUT_MS, 5000);