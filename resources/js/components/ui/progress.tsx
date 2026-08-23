"use client"

import * as React from "react"
import * as ProgressPrimitive from "@radix-ui/react-progress"

import { cn } from "@/lib/utils"

type ProgressTone = "default" | "run" | "caution" | "stop" | "info" | "neutral"

function Progress({
  className,
  indicatorClassName,
  tone = "default",
  value,
  ...props
}: React.ComponentProps<typeof ProgressPrimitive.Root> & {
  indicatorClassName?: string
  tone?: ProgressTone
}) {
  const indicatorTone: Record<ProgressTone, string> = {
    default: "bg-primary",
    run: "bg-nx-andon-run",
    caution: "bg-nx-andon-caution",
    stop: "bg-nx-andon-stop",
    info: "bg-nx-andon-info",
    neutral: "bg-nx-n-400",
  }

  const trackTone: Record<ProgressTone, string> = {
    default: "bg-primary/20",
    run: "bg-nx-andon-run-bg",
    caution: "bg-nx-andon-caution-bg",
    stop: "bg-nx-andon-stop-bg",
    info: "bg-nx-andon-info-bg",
    neutral: "bg-nx-n-100",
  }

  return (
    <ProgressPrimitive.Root
      data-slot="progress"
      className={cn(
        "relative h-2 w-full overflow-hidden rounded-full",
        trackTone[tone],
        className
      )}
      {...props}
    >
      <ProgressPrimitive.Indicator
        data-slot="progress-indicator"
        className={cn("h-full w-full flex-1 transition-all", indicatorTone[tone], indicatorClassName)}
        style={{ transform: `translateX(-${100 - (value || 0)}%)` }}
      />
    </ProgressPrimitive.Root>
  )
}

export { Progress }
