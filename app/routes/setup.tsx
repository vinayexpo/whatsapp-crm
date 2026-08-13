import { useEffect, useState, type FormEvent } from "react";
import { useNavigate } from "react-router";
import Box from "@mui/material/Box";
import Paper from "@mui/material/Paper";
import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import TextField from "@mui/material/TextField";
import Button from "@mui/material/Button";
import Alert from "@mui/material/Alert";
import WhatsAppIcon from "@mui/icons-material/WhatsApp";
import { apiClient, ApiError } from "~/utils/api-client";
import { useAuth } from "~/hooks/use-auth";
import { useBootstrapStatus } from "~/hooks/use-bootstrap-status";
import type { Route } from "./+types/setup";

export function meta({}: Route.MetaArgs) {
  return [{ title: "Create Superadmin — Creative Connects" }];
}

export default function Setup() {
  const navigate = useNavigate();
  const { setUser, setStatus } = useAuth();
  const bootstrapStatus = useBootstrapStatus();
  const [name, setName] = useState("");
  const [email, setEmail] = useState("");
  const [password, setPassword] = useState("");
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);

  useEffect(() => {
    if (bootstrapStatus === "ready") {
      navigate("/login", { replace: true });
    }
  }, [bootstrapStatus, navigate]);

  async function handleSubmit(event: FormEvent) {
    event.preventDefault();
    setError(null);
    setSubmitting(true);
    try {
      const user = await apiClient.setupSuperadmin({ name, email, password });
      setUser(user);
      setStatus("authenticated");
      navigate("/superadmin", { replace: true });
    } catch (err) {
      if (err instanceof ApiError) {
        setError(err.errors?.email?.[0] ?? err.message);
      } else {
        setError("Something went wrong. Please try again.");
      }
    } finally {
      setSubmitting(false);
    }
  }

  if (bootstrapStatus !== "needs-superadmin") {
    return null;
  }

  return (
    <Box
      sx={{
        minHeight: "100vh",
        display: "flex",
        alignItems: "center",
        justifyContent: "center",
        bgcolor: "background.default",
        px: 2,
      }}
    >
      <Paper elevation={3} sx={{ p: 4, width: "100%", maxWidth: 400 }}>
        <Stack spacing={3} component="form" onSubmit={handleSubmit}>
          <Stack direction="row" spacing={1.25} sx={{ alignItems: "center" }}>
            <Box
              sx={{
                width: 36,
                height: 36,
                borderRadius: "10px",
                bgcolor: "primary.main",
                display: "flex",
                alignItems: "center",
                justifyContent: "center",
              }}
            >
              <WhatsAppIcon sx={{ color: "primary.contrastText", fontSize: 20 }} />
            </Box>
            <Typography variant="h6" sx={{ fontWeight: 700 }}>
              Creative Connects
            </Typography>
          </Stack>

          <Typography variant="body2" color="text.secondary">
            No superadmin exists yet. Create the first superadmin account to set up the system.
          </Typography>

          {error ? <Alert severity="error">{error}</Alert> : null}

          <TextField
            label="Full name"
            value={name}
            onChange={(e) => setName(e.target.value)}
            required
            autoFocus
            fullWidth
          />

          <TextField
            label="Email"
            type="email"
            value={email}
            onChange={(e) => setEmail(e.target.value)}
            required
            fullWidth
          />

          <TextField
            label="Password"
            type="password"
            value={password}
            onChange={(e) => setPassword(e.target.value)}
            required
            helperText="Minimum 8 characters"
            fullWidth
          />

          <Button type="submit" variant="contained" size="large" disabled={submitting} fullWidth>
            {submitting ? "Creating…" : "Create Superadmin"}
          </Button>
        </Stack>
      </Paper>
    </Box>
  );
}
