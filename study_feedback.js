/* Annotate only failures that occur at the actual global stopping tick.
 * Movement, timing, positions, success, and failure reasons remain engine-owned.
 */
function studyGlobalResult(scene, rules) {
  const result = simulate(scene, rules);
  if (result.ok) return {...result, failedEncounterIds:[], encounterResults:[]};
  const final = result.frames.at(-1);
  const stopTick = final.tick;
  const groups = new Map();
  for (const agent of scene.agents.filter(a => a.active)) {
    const key = String(agent.moduleId);
    if (!groups.has(key)) groups.set(key, []);
    groups.get(key).push(agent);
  }
  const failed = [];
  const events = [];
  const meta = {...final.agents};
  for (const [region, agents] of groups) {
    const local = simulate({...scene, agents}, rules);
    const end = local.frames.at(-1);
    if (!local.ok && end.tick <= stopTick) {
      failed.push(region);
      events.push(end.event);
      for (const agent of agents) {
        if (end.agents[agent.id]?.failed) {
          meta[agent.id] = {...meta[agent.id], failed:true, state:"failed"};
        }
      }
    }
  }
  return {...result, continuationMode:"global", failedEncounterIds:failed,
    observedFailureTick:stopTick,
    frames:[...result.frames.slice(0, -1), {...final, agents:meta, simultaneousEvents:events}],
    encounterResults:failed.map(id => ({encounter_id:id, ok:false, observed_at_tick:stopTick}))};
}
