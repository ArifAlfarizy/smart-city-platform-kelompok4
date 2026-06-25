import express from "express";
import {
  services,
  dashboardPaths,
  FETCH_TIMEOUT_MS,
} from "../config/services.js";

const dashboardRouter = express.Router();

const fetchJson = async (url, authHeader) => {
  const controller = new AbortController();
  const timer = setTimeout(() => controller.abort(), FETCH_TIMEOUT_MS);

  try {
    const response = await fetch(url, {
      headers: authHeader ? { Authorization: authHeader } : {},
      signal: controller.signal,
    });

    const body = await response.json().catch(() => null);
    return { ok: response.ok, status: response.status, body };
  } catch (err) {
    return {
      ok: false,
      status: 0,
      error: err.name === "AbortError" ? "Timeout" : err.message,
    };
  } finally {
    clearTimeout(timer);
  }
};

const formatResult = (result, fallbackLabel) =>
  result.ok
    ? result.body
    : {
        error:
          result.error ?? `${fallbackLabel} merespons status ${result.status}`,
      };

dashboardRouter.get("/", async (req, res) => {
  const authHeader = req.headers.authorization;

  const [traffic, environment, flood, incidents] = await Promise.all([
    fetchJson(
      `${services.traffic}${dashboardPaths.trafficSummary}`,
      authHeader,
    ),
    fetchJson(
      `${services.environment}${dashboardPaths.environmentStatus}`,
      authHeader,
    ),
    fetchJson(
      `${services.environment}${dashboardPaths.environmentFlood}`,
      authHeader,
    ),
    fetchJson(
      `${services.citizen}${dashboardPaths.citizenIncidents}`,
      authHeader,
    ),
  ]);

  const allOk = [traffic, environment, flood, incidents].every((r) => r.ok);

  return res.status(200).json({
    success: true,
    partial: !allOk,
    message: allOk
      ? "Dashboard data berhasil diambil dari semua service."
      : "Sebagian data dashboard gagal diambil — cek field yang isinya { error }.",
    generated_at: new Date().toISOString(),
    data: {
      traffic: formatResult(traffic, "Traffic Service"),
      environment: formatResult(environment, "Environment Service"),
      flood: formatResult(flood, "Environment Service (flood)"),
      incidents: formatResult(incidents, "Citizen Service"),
    },
  });
});

export default dashboardRouter;
