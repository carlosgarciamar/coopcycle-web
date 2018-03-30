import React from 'react'
import _ from 'lodash'
import Task from './Task'
import TaskGroup from './TaskGroup'
import { connect } from 'react-redux'
import { setTaskListGroupMode } from '../store/actions'

class UnassignedTasks extends React.Component {

  componentDidMount() {

    const $groupModeBtn = $('#task-list-group-mode')

    $(document).on('click', '#task-list-group-mode--group', () => {
      this.props.setTaskListGroupMode('GROUP_MODE_FOLDERS')
      $groupModeBtn.popover('hide')
    })

    $(document).on('click', '#task-list-group-mode--none', () => {
      this.props.setTaskListGroupMode('GROUP_MODE_NONE')
      $groupModeBtn.popover('hide')
    })

    $groupModeBtn.popover({
      container: 'body',
      html: true,
      placement: 'left',
      content: document.querySelector('#task-list-group-mode-template').textContent
    })
  }

  renderGroup(group, tasks) {
    return (
      <TaskGroup key={ group.id } group={ group } tasks={ tasks } />
    )
  }

  render() {

    const { taskListGroupMode, selectedTags, showUntaggedTasks } = this.props
    let { unassignedTasks } = this.props
    const groupsMap = new Map()
    const groups = []
    let standaloneTasks = []

    // tag filtering - task should have at least one of the selected tags
    unassignedTasks = _.filter(unassignedTasks, (task) =>
      (task.tags.length > 0 &&_.intersectionBy(task.tags, selectedTags, 'name').length > 0) ||
      (task.tags.length === 0 && showUntaggedTasks)
    )

    if (taskListGroupMode === 'GROUP_MODE_FOLDERS') {

      const tasksWithGroup = _.filter(unassignedTasks, task => task.hasOwnProperty('group') && task.group)

      _.forEach(tasksWithGroup, task => {
        const keys = Array.from(groupsMap.keys())
        const group = _.find(keys, group => group.id === task.group.id)
        if (!group) {
          groupsMap.set(task.group, [ task ])
        } else {
          groupsMap.get(group).push(task)
        }
      })
      groupsMap.forEach((tasks, group) => {
        groups.push(this.renderGroup(group, tasks))
      })

      standaloneTasks = _.filter(unassignedTasks, task => !task.hasOwnProperty('group') || !task.group)

    } else {
      standaloneTasks = unassignedTasks
    }

    return (
      <div className="dashboard__panel">
        <h4>
          <span>{ window.AppData.Dashboard.i18n['Unassigned'] }</span>
          <span className="pull-right">
            <a href="#" id="task-list-group-mode" title={ window.AppData.Dashboard.i18n['Display'] }>
              <i className="fa fa-list"></i>
            </a>   <a href="#" onClick={ e => {
              e.preventDefault();
              $('#task-modal').modal('show')
            }}>
              <i className="fa fa-plus"></i>
            </a>
          </span>
        </h4>
        <div className="dashboard__panel__scroll">
          <div className="list-group nomargin">
            { groups }
            { _.map(standaloneTasks, (task, key) => {
              return (
                <Task
                  key={ key }
                  task={ task }
                />
              )
            })}
          </div>
        </div>
      </div>
    )
  }
}

function mapStateToProps (state) {
  return {
    unassignedTasks: state.unassignedTasks,
    taskListGroupMode: state.taskListGroupMode,
    selectedTags: state.tagsFilter.selectedTagsList,
    showUntaggedTasks: state.tagsFilter.showUntaggedTasks
  }
}

function mapDispatchToProps(dispatch) {
  return {
    setTaskListGroupMode: (mode) => { dispatch(setTaskListGroupMode(mode)) },
  }
}

export default connect(mapStateToProps, mapDispatchToProps)(UnassignedTasks)
