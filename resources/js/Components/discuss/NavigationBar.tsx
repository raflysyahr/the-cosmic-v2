import { useMemo, useState } from "react";
import {
  Search,
  MoreVertical,
  User,
  Users,
  MessageCircle,
  Circle,
  CircleFadingPlus,
  Image as ImageIcon,
  Plus,
} from "lucide-react";

export default function NavigationBar({ active, setActive }) {
  const items = [
    {
      id: "chats",
      label: "Chats",
      icon: MessageCircle,
    },
    {
      id: "story",
      label: "Story",
      icon: CircleFadingPlus,
    },
    {
      id: "profile",
      label: "Profile",
      icon: User,
    },
  ];

  return (
    <nav className="shrink-0 z-40 w-full max-w-md border-t border-neutral-900 bg-black/95 px-8 py-2.5 backdrop-blur-md">
      <div className="flex items-center justify-between">
        {items.map((item) => {
          const Icon = item.icon;
          const isActive = active === item.id;

          return (
            <button
              key={item.id}
              onClick={() => setActive(item.id)}
              className="relative flex flex-col items-center px-4 pb-2"
            >
              <Icon
                size={18}
                strokeWidth={isActive ? 2 : 1.8}
                className={
                  isActive
                    ? "text-white"
                    : "text-neutral-500 transition-colors group-hover:text-neutral-300"
                }
              />

              <span
                className={`mt-1 text-[11px] font-medium ${
                  isActive
                    ? "text-white"
                    : "text-neutral-500"
                }`}
              >
                {item.label}
              </span>                                                                                                                                                                   {isActive && (
                <span className="mt-1.5 h-[2px] w-[2px] rounded-[2px] bg-white" />
              )}
            </button>
          );
        })}
      </div>
    </nav>
  );
}
