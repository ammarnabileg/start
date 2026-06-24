"use client";

import { cn } from "@/lib/utils";
import React from "react";

interface InputProps extends React.InputHTMLAttributes<HTMLInputElement> {
  label?: string;
  error?: string;
  leftIcon?: React.ReactNode;
  rightIcon?: React.ReactNode;
}

export function Input({ label, error, leftIcon, rightIcon, className, id, ...props }: InputProps) {
  const inputId = id || label?.toLowerCase().replace(/\s/g, "-");
  return (
    <div className="w-full">
      {label && <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>}
      <div className="relative">
        {leftIcon && <div className="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">{leftIcon}</div>}
        <input
          id={inputId}
          className={cn("w-full px-3 py-2.5 text-sm bg-white border rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow duration-150",
            leftIcon && "pr-10",
            rightIcon && "pl-10",
            error ? "border-red-400" : "border-gray-300",
            className
          )}
          {...props}
        />
        {rightIcon && <div className="absolute left-3 top-1/2 -translate-y-1/2 text-gray-400 pointer-events-none">{rightIcon}</div>}
      </div>
      {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
    </div>
  );
}

interface TextareaProps extends React.TextareaHTMLAttributes<HTMLTextAreaElement> {
  label?: string;
  error?: string;
}

export function Textarea({ label, error, className, id, ...props }: TextareaProps) {
  const inputId = id || label?.toLowerCase().replace(/\s/g, "-");
  return (
    <div className="w-full">
      {label && <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>}
      <textarea
        id={inputId}
        className={cn("w-full px-3 py-2.5 text-sm bg-white border rounded-lg text-gray-900 placeholder:text-gray-400 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow duration-150 resize-none",
          error ? "border-red-400" : "border-gray-300",
          className
        )}
        {...props}
      />
      {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
    </div>
  );
}

interface SelectProps extends React.SelectHTMLAttributes<HTMLSelectElement> {
  label?: string;
  error?: string;
}

export function Select({ label, error, className, children, id, ...props }: SelectProps) {
  const inputId = id || label?.toLowerCase().replace(/\s/g, "-");
  return (
    <div className="w-full">
      {label && <label htmlFor={inputId} className="block text-sm font-medium text-gray-700 mb-1.5">{label}</label>}
      <select
        id={inputId}
        className={cn("w-full px-3 py-2.5 text-sm bg-white border rounded-lg text-gray-900 focus:outline-none focus:ring-2 focus:ring-violet-500 focus:border-transparent transition-shadow duration-150 appearance-none cursor-pointer",
          error ? "border-red-400" : "border-gray-300",
          className
        )}
        {...props}
      >
        {children}
      </select>
      {error && <p className="mt-1.5 text-xs text-red-500">{error}</p>}
    </div>
  );
}
