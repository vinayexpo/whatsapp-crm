import Stack from "@mui/material/Stack";
import Typography from "@mui/material/Typography";
import ToggleButton from "@mui/material/ToggleButton";
import ToggleButtonGroup from "@mui/material/ToggleButtonGroup";
import { DatePicker } from "@mui/x-date-pickers/DatePicker";
import type { Dayjs } from "dayjs";

export type DateRangePreset = "7d" | "14d" | "30d" | "custom";

export interface DateRangeFilterProps {
  preset: DateRangePreset;
  onPresetChange: (preset: DateRangePreset) => void;
  start: Dayjs | null;
  end: Dayjs | null;
  onStartChange: (value: Dayjs | null) => void;
  onEndChange: (value: Dayjs | null) => void;
  minDate?: Dayjs;
  maxDate?: Dayjs;
  presets?: DateRangePreset[];
}

const PRESET_LABELS: Record<DateRangePreset, string> = {
  "7d": "7D",
  "14d": "14D",
  "30d": "30D",
  custom: "Custom",
};

export function DateRangeFilter({
  preset,
  onPresetChange,
  start,
  end,
  onStartChange,
  onEndChange,
  minDate,
  maxDate,
  presets = ["7d", "14d", "30d", "custom"],
}: DateRangeFilterProps) {
  return (
    <Stack direction="row" sx={{ alignItems: "center", flexWrap: "wrap", gap: 1.5 }}>
      <ToggleButtonGroup
        size="small"
        exclusive
        value={preset}
        onChange={(_, value) => value && onPresetChange(value)}
      >
        {presets.map((p) => (
          <ToggleButton key={p} value={p}>
            {PRESET_LABELS[p]}
          </ToggleButton>
        ))}
      </ToggleButtonGroup>
      {preset === "custom" && (
        <Stack direction="row" sx={{ alignItems: "center", gap: 1 }}>
          <DatePicker
            label="Start"
            value={start}
            onChange={onStartChange}
            maxDate={end ?? maxDate}
            minDate={minDate}
            slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
          />
          <Typography variant="body2" sx={{ color: "text.secondary" }}>
            to
          </Typography>
          <DatePicker
            label="End"
            value={end}
            onChange={onEndChange}
            minDate={start ?? minDate}
            maxDate={maxDate}
            slotProps={{ textField: { size: "small", sx: { width: 150 } } }}
          />
        </Stack>
      )}
    </Stack>
  );
}
