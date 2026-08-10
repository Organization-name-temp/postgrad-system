import type { StatusChipProps } from '../../shared/ui/StatusChip'

/**
 * The must-retake warning chip shown on enrollment surfaces when a prep/core course still
 * carries an unresolved below-C+ obligation. The caller passes the already-localized label.
 */
export function mustRetakeChip(label: string): StatusChipProps {
  return {
    tone: 'warning',
    variant: 'filled',
    label,
  }
}
