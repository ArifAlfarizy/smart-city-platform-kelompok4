export const requireRole =
  (...allowedRoles) =>
  (req, res, next) => {
    const role = req.user?.role;

    if (!role || !allowedRoles.includes(role)) {
      return res.status(403).json({
        success: false,
        message: "Forbidden. Role kamu tidak punya akses ke endpoint ini.",
      });
    }

    next();
  };
