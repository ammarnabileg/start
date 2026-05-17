"use client";
import { PlatformIcon } from "@/components/ui/PlatformIcon";
import { Heart, MessageSquare, Share2, Bookmark } from "lucide-react";

interface PostPreviewProps {
  platform: string;
  content: string;
  mediaUrl?: string;
  username?: string;
  avatar?: string;
}

export function PostPreview({ platform, content, mediaUrl, username = "yourbrand", avatar }: PostPreviewProps) {
  const isInstagram = platform === "instagram";
  const isLinkedIn = platform === "linkedin";
  const isTikTok = platform === "tiktok";
  const isTwitter = platform === "twitter";

  return (
    <div className="bg-white rounded-2xl overflow-hidden shadow-2xl max-w-sm">
      {/* Platform header */}
      <div className="bg-gray-50 px-4 py-3 flex items-center gap-2 border-b border-gray-100">
        <PlatformIcon platform={platform} size="sm" />
        <span className="text-xs font-semibold text-gray-600 capitalize">{platform} Preview</span>
      </div>

      {/* Post content */}
      <div className="p-4">
        <div className="flex items-center gap-2 mb-3">
          <div className="w-8 h-8 rounded-full bg-gradient-to-br from-blue-500 to-purple-500 flex items-center justify-center text-white text-xs font-bold">
            {username[0].toUpperCase()}
          </div>
          <div>
            <p className="text-xs font-bold text-gray-900">@{username}</p>
            <p className="text-[10px] text-gray-400">Just now</p>
          </div>
        </div>

        {mediaUrl && (
          <div className="w-full aspect-square bg-gray-200 rounded-xl mb-3 overflow-hidden">
            <img src={mediaUrl} alt="Post media" className="w-full h-full object-cover" />
          </div>
        )}

        <p className="text-sm text-gray-800 leading-relaxed whitespace-pre-wrap line-clamp-4">{content}</p>

        {isInstagram && (
          <div className="flex items-center gap-4 mt-3 pt-3 border-t border-gray-100">
            <button className="flex items-center gap-1 text-gray-500 text-xs"><Heart size={14} /> 0</button>
            <button className="flex items-center gap-1 text-gray-500 text-xs"><MessageSquare size={14} /> 0</button>
            <button className="flex items-center gap-1 text-gray-500 text-xs"><Share2 size={14} /></button>
            <button className="ml-auto text-gray-500"><Bookmark size={14} /></button>
          </div>
        )}
      </div>
    </div>
  );
}
