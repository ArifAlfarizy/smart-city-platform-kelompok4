import { createProxyMiddleware } from "http-proxy-middleware";

export const makeProxy = (target) =>
  createProxyMiddleware({
    target,
    changeOrigin: true,
    onProxyReq: (proxyReq, req) => {
      if (req.user) {
        if (req.user.id) proxyReq.setHeader("X-User-Id", String(req.user.id));
        if (req.user.role)
          proxyReq.setHeader("X-User-Role", String(req.user.role));
        if (req.user.email)
          proxyReq.setHeader("X-User-Email", String(req.user.email));
        if (req.user.client_id)
          proxyReq.setHeader("X-Client-Id", String(req.user.client_id));
      }
    },
    onError: (err, req, res) => {
      console.error(
        `[gateway] Proxy error -> ${target}${req.originalUrl}:`,
        err.message,
      );
      if (!res.headersSent) {
        res.writeHead(502, { "Content-Type": "application/json" });
      }
      res.end(
        JSON.stringify({
          success: false,
          message: `Service tidak dapat dihubungi: ${target}`,
        }),
      );
    },
  });
