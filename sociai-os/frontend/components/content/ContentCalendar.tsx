"use client";

import { useState } from "react";
import { motion } from "framer-motion";
import { ChevronLeft, ChevronRight, Plus } from "lucide-react";
import { cn } from "@/lib/utils";

interface CalendarPost {
  id: string;
  title: string;
  platform: string;
  time: string;
  status: "draft" | "scheduled" | "published";
  color: string;
}

const PLATFORM_COLORS: Record<string, string> = {
  instagram: "#E1306C",
  facebook: "#1877F2",
  twitter: "#1DA1F2",
  tiktok: "#FF0050",
  youtube: "#FF0000",
  linkedin: "#0A66C2",
};

const MOCK_POSTS: Record<string, CalendarPost[]> = {
  "2": [{ id: "p1", title: "Morning Motivation", platform: "instagram", time: "9:00 AM", status: "published", color: "#E1306C" }],
  "5": [
    { id: "p2", title: "Product Launch Teaser", platform: "twitter", time: "2:00 PM", status: "scheduled", color: "#1DA1F2" },
    { id: "p3", title: "Behind the Scenes", platform: "facebook", time: "4:30 PM", status: "scheduled", color: "#1877F2" },
  ],
  "8": [{ id: "p4", title: "Tutorial Video", platform: "youtube", time: "11:00 AM", status: "draft", color: "#FF0000" }],
  "12": [
    { id: "p5", title: "Industry Insights", platform: "linkedin", time: "8:00 AM", status: "scheduled", color: "#0A66C2" },
    { id: "p6", title: "Dance Trend", platform: "tiktok", time: "6:00 PM", status: "scheduled", color: "#FF0050" },
  ],
  "15": [{ id: "p7", title: "Weekend Poll", platform: "instagram", time: "12:00 PM", status: "draft", color: "#E1306C" }],
  "18": [{ id: "p8", title: "Case Study", platform: "linkedin", time: "9:00 AM", status: "scheduled", color: "#0A66C2" }],
  "22": [
    { id: "p9", title: "Flash Sale Post", platform: "facebook", time: "10:00 AM", status: "scheduled", color: "#1877F2" },
    { id: "p10", title: "Story Sequence", platform: "instagram", time: "3:00 PM", status: "draft", color: "#E1306C" },
  ],
  "25": [{ id: "p11", title: "Monthly Recap", platform: "twitter", time: "1:00 PM", status: "draft", color: "#1DA1F2" }],
  "28": [{ id: "p12", title: "New Arrivals", platform: "pinterest", time: "11:00 AM", status: "scheduled", color: "#E60023" }],
};

const DAYS = ["Sun", "Mon", "Tue", "Wed", "Thu", "Fri", "Sat"];
const MONTHS = ["January", "February", "March", "April", "May", "June", "July", "August", "September", "October", "November", "December"];

const STATUS_COLORS = {
  draft: "rgba(107,114,128,0.5)",
  scheduled: "rgba(139,92,246,0.8)",
  published: "rgba(16,185,129,0.8)",
};

export function ContentCalendar() {
  const today = new Date();
  const [currentDate, setCurrentDate] = useState(new Date(today.getFullYear(), today.getMonth(), 1));
  const [selectedDay, setSelectedDay] = useState<number | null>(null);

  const year = currentDate.getFullYear();
  const month = currentDate.getMonth();
  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const prevMonth = () => setCurrentDate(new Date(year, month - 1, 1));
  const nextMonth = () => setCurrentDate(new Date(year, month + 1, 1));

  const cells: { day: number | null; key: string }[] = [
    ...Array.from({ length: firstDay }, (_, i) => ({ day: null as null, key: `empty-${i}` })),
    ...Array.from({ length: daysInMonth }, (_, i) => ({ day: i + 1, key: `day-${i + 1}` })),
  ];

  return (
    <div className="glass-panel rounded-2xl overflow-hidden">
      {/* Header */}
      <div className="flex items-center justify-between p-5 border-b border-white/8">
        <h2 className="text-white font-semibold text-lg">
          {MONTHS[month]} <span className="text-white/40">{year}</span>
        </h2>
        <div className="flex items-center gap-2">
          <button onClick={prevMonth} className="w-8 h-8 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-white transition-all">
            <ChevronLeft className="w-4 h-4" />
          </button>
          <button
            onClick={() => setCurrentDate(new Date(today.getFullYear(), today.getMonth(), 1))}
            className="px-3 py-1.5 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 text-white/50 text-xs font-medium transition-all"
          >
            Today
          </button>
          <button onClick={nextMonth} className="w-8 h-8 rounded-lg bg-white/5 border border-white/10 hover:bg-white/10 flex items-center justify-center text-white/50 hover:text-white transition-all">
            <ChevronRight className="w-4 h-4" />
          </button>
        </div>
      </div>

      {/* Legend */}
      <div className="flex items-center gap-4 px-5 py-2.5 bg-white/2 border-b border-white/5">
        {(["draft", "scheduled", "published"] as const).map(s => (
          <div key={s} className="flex items-center gap-1.5">
            <div className="w-2 h-2 rounded-full" style={{ background: STATUS_COLORS[s] }} />
            <span className="text-white/40 text-xs capitalize">{s}</span>
          </div>
        ))}
      </div>

      {/* Day headers */}
      <div className="grid grid-cols-7 border-b border-white/5">
        {DAYS.map(d => (
          <div key={d} className="text-center text-white/30 text-xs font-semibold py-2.5">
            {d}
          </div>
        ))}
      </div>

      {/* Calendar grid */}
      <div className="grid grid-cols-7">
        {cells.map(({ day, key }) => {
          const posts = day ? (MOCK_POSTS[String(day)] || []) : [];
          const isToday = day === today.getDate() && month === today.getMonth() && year === today.getFullYear();
          const isSelected = day === selectedDay;

          return (
            <div
              key={key}
              onClick={() => day && setSelectedDay(day === selectedDay ? null : day)}
              className={cn(
                "min-h-[90px] p-2 border-r border-b border-white/5 last:border-r-0 transition-all",
                day ? "cursor-pointer hover:bg-white/3" : "bg-white/[0.01]",
                isSelected && "bg-electric-blue/5",
              )}
            >
              {day && (
                <>
                  <div className="flex items-center justify-between mb-1.5">
                    <span
                      className={cn(
                        "w-6 h-6 rounded-full flex items-center justify-center text-xs font-semibold",
                        isToday
                          ? "bg-electric-blue text-white"
                          : "text-white/60 hover:text-white"
                      )}
                    >
                      {day}
                    </span>
                    {posts.length > 0 && (
                      <span className="text-white/30 text-xs">{posts.length}</span>
                    )}
                  </div>

                  <div className="space-y-0.5">
                    {posts.slice(0, 2).map(post => (
                      <motion.div
                        key={post.id}
                        whileHover={{ scale: 1.02 }}
                        className="flex items-center gap-1 px-1.5 py-0.5 rounded text-xs truncate"
                        style={{
                          background: `${post.color}18`,
                          borderLeft: `2px solid ${post.color}`,
                        }}
                      >
                        <span className="truncate text-white/60">{post.title}</span>
                      </motion.div>
                    ))}
                    {posts.length > 2 && (
                      <span className="text-white/30 text-xs pl-1">+{posts.length - 2} more</span>
                    )}
                  </div>

                  {posts.length === 0 && (
                    <button className="w-full mt-1 flex items-center justify-center opacity-0 hover:opacity-100 transition-opacity group-hover:opacity-100">
                      <Plus className="w-3 h-3 text-white/20 hover:text-white/40" />
                    </button>
                  )}
                </>
              )}
            </div>
          );
        })}
      </div>

      {/* Selected day detail */}
      {selectedDay && MOCK_POSTS[String(selectedDay)] && (
        <motion.div
          initial={{ opacity: 0, height: 0 }}
          animate={{ opacity: 1, height: "auto" }}
          className="border-t border-white/8 p-4"
        >
          <h4 className="text-white/60 text-xs font-semibold uppercase tracking-wider mb-3">
            {MONTHS[month]} {selectedDay} — {MOCK_POSTS[String(selectedDay)]?.length} post{(MOCK_POSTS[String(selectedDay)]?.length || 0) > 1 ? "s" : ""}
          </h4>
          <div className="space-y-2">
            {MOCK_POSTS[String(selectedDay)]?.map(post => (
              <div key={post.id} className="flex items-center gap-3 p-2.5 rounded-lg bg-white/5 border border-white/10">
                <div className="w-2 h-2 rounded-full flex-shrink-0" style={{ background: post.color }} />
                <span className="text-white text-sm font-medium flex-1">{post.title}</span>
                <span className="text-white/30 text-xs">{post.time}</span>
                <span
                  className="text-xs px-2 py-0.5 rounded-full font-medium"
                  style={{
                    background: STATUS_COLORS[post.status] + "30",
                    color: STATUS_COLORS[post.status],
                  }}
                >
                  {post.status}
                </span>
              </div>
            ))}
          </div>
        </motion.div>
      )}
    </div>
  );
}

export default ContentCalendar;
