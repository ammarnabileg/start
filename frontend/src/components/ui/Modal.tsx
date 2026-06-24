"use client";

import { cn } from "@/lib/utils";
import { X } from "lucide-react";
import React, { useEffect } from "react";

interface ModalProps {
  open: boolean;
  onClose: () => void;
  title?: string;
  children: React.ReactNode;
  size?: "sm" | "md" | "lg" | "xl" | "full";
  footer?: React.ReactNode;
}

const sizes = {
  sm: "max-w-sm",
  md: "max-w-md",
  lg: "max-w-lg",
  xl: "max-w-2xl",
  full: "max-w-5xl",
};

export function Modal({ open, onClose, title, children, size = "md", footer }: ModalProps) {
  useEffect(() => {
    if (open) document.body.style.overflow = "hidden";
    else document.body.style.overflow = "";
    return () => { document.body.style.overflow = ""; };
  }, [open]);

  if (!open) return null;

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4">
      <div className="absolute inset-0 bg-black/40 backdrop-blur-sm" onClick={onClose} />
      <div className={cn("relative w-full bg-white rounded-2xl shadow-xl flex flex-col max-h-[90vh] animate-fade-in", sizes[size])}>
        {title && (
          <div className="flex items-center justify-between px-6 py-4 border-b border-gray-200">
            <h2 className="text-lg font-bold text-gray-900">{title}</h2>
            <button onClick={onClose} className="p-1.5 rounded-lg text-gray-400 hover:text-gray-600 hover:bg-gray-100 transition-colors">
              <X className="w-5 h-5" />
            </button>
          </div>
        )}
        <div className="flex-1 overflow-y-auto p-6">{children}</div>
        {footer && <div className="px-6 py-4 border-t border-gray-200 bg-gray-50 rounded-b-2xl flex items-center gap-3 justify-end">{footer}</div>}
      </div>
    </div>
  );
}
