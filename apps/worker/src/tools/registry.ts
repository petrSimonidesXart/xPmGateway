import type { ToolFunction } from './types.js';
import { pmLogin } from './pm/login.js';
import { pmOpenProject } from './pm/openProject.js';
import { pmOpenTask } from './pm/openTask.js';
import { pmReadTask } from './pm/readTask.js';
import { pmCreateComment } from './pm/createComment.js';
import { pmCreateSubtask } from './pm/createSubtask.js';
import { pmCloseTask } from './pm/closeTask.js';
import { pmUpdateTask } from './pm/updateTask.js';
import { pmSearchSubtasks } from './pm/searchSubtasks.js';
import { pmSearchComments } from './pm/searchComments.js';
import { pmCloseSubtask } from './pm/closeSubtask.js';
import { pmUpdateSubtask } from './pm/updateSubtask.js';
import { pmTimeTrack } from './pm/timeTrack.js';
import { pmExportCsv } from './pm/exportCsv.js';

/**
 * Registry of all available tool functions.
 * Each tool can run standalone (own browser) or within a scenario (shared browser).
 */
export const toolRegistry: Record<string, ToolFunction> = {
	pm_login: pmLogin,
	pm_open_project: pmOpenProject,
	pm_open_task: pmOpenTask,
	pm_read_task: pmReadTask,
	pm_create_comment: pmCreateComment,
	pm_create_subtask: pmCreateSubtask,
	pm_close_task: pmCloseTask,
	pm_update_task: pmUpdateTask,
	pm_search_subtasks: pmSearchSubtasks,
	pm_search_comments: pmSearchComments,
	pm_close_subtask: pmCloseSubtask,
	pm_update_subtask: pmUpdateSubtask,
	pm_time_track: pmTimeTrack,
	pm_export_csv: pmExportCsv,
};
