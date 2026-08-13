import { useRef, useState } from "react";
import Alert from "@mui/material/Alert";
import Avatar from "@mui/material/Avatar";
import Box from "@mui/material/Box";
import Button from "@mui/material/Button";
import Stack from "@mui/material/Stack";
import TextField from "@mui/material/TextField";
import Typography from "@mui/material/Typography";
import { apiClient, ApiError } from "~/utils/api-client";
import { useAuth } from "~/hooks/use-auth";

export function MyProfileForm() {
  const { user, setUser } = useAuth();
  const fileInputRef = useRef<HTMLInputElement>(null);
  const [name, setName] = useState(user?.name ?? "");
  const [previewUrl, setPreviewUrl] = useState<string | null>(null);
  const [avatarFile, setAvatarFile] = useState<File | null>(null);
  const [error, setError] = useState<string | null>(null);
  const [submitting, setSubmitting] = useState(false);
  const [saved, setSaved] = useState(false);

  if (!user) {
    return null;
  }

  function handleFileChange(event: React.ChangeEvent<HTMLInputElement>) {
    const file = event.target.files?.[0] ?? null;
    setAvatarFile(file);
    setSaved(false);
    setPreviewUrl(file ? URL.createObjectURL(file) : null);
  }

  async function handleSave() {
    setError(null);
    setSaved(false);
    setSubmitting(true);
    try {
      const updated = await apiClient.updateProfile({
        name: name !== user!.name ? name : undefined,
        avatar: avatarFile ?? undefined,
      });
      setUser(updated);
      setAvatarFile(null);
      setPreviewUrl(null);
      setSaved(true);
    } catch (err) {
      setError(err instanceof ApiError ? err.message : "Something went wrong. Please try again.");
    } finally {
      setSubmitting(false);
    }
  }

  const canSave = !submitting && (avatarFile !== null || name !== user.name);

  return (
    <Stack spacing={3} sx={{ maxWidth: 480 }}>
      <Typography variant="body2" sx={{ color: "text.secondary" }}>
        Update your display name and profile picture.
      </Typography>

      {error ? <Alert severity="error">{error}</Alert> : null}
      {saved ? <Alert severity="success">Profile updated.</Alert> : null}

      <Stack direction="row" spacing={2.5} sx={{ alignItems: "center" }}>
        <Avatar src={previewUrl ?? user.avatarUrl} alt={user.name} sx={{ width: 72, height: 72 }} />
        <Stack spacing={1}>
          <Button variant="outlined" size="small" onClick={() => fileInputRef.current?.click()}>
            Choose photo
          </Button>
          <Typography variant="caption" sx={{ color: "text.secondary" }}>
            JPG or PNG, up to 2MB.
          </Typography>
          <Box
            component="input"
            ref={fileInputRef}
            type="file"
            accept="image/*"
            onChange={handleFileChange}
            sx={{ display: "none" }}
          />
        </Stack>
      </Stack>

      <TextField
        label="Full name"
        value={name}
        onChange={(e) => {
          setName(e.target.value);
          setSaved(false);
        }}
        fullWidth
      />

      <Box>
        <Button variant="contained" disabled={!canSave} onClick={handleSave}>
          {submitting ? "Saving…" : "Save changes"}
        </Button>
      </Box>
    </Stack>
  );
}
