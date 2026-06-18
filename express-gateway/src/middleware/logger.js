export const requestLogger = (req, res, next) => {
  const start = Date.now();

  res.on("finish", () => {
    const responseTimeMs = Date.now() - start;
    console.log(
      JSON.stringify({
        timestamp: new Date().toISOString(),
        method: req.method,
        path: req.originalUrl,
        status: res.statusCode,
        responseTimeMs,
      }),
    );
  });

  next();
};
